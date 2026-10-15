<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingComment;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\ListingCommentedNotification;
use App\Support\GuestName;
use App\Support\MailSettings;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * İlan detayındaki yorumlar: herkes okur ve yazar (üyelik/onay yok). Misafirler IP'ye bağlı
 * "Misafir04217" gibi bir adla görünür. Kötüye kullanıma karşı IP başına hız sınırı (route) ve
 * robotların doldurduğu gizli "website" alanı; admin panelden silinir.
 */
class ListingCommentController extends Controller
{
    public const PER_PAGE = 20;

    public function index(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->status === ListingStatus::Approved, 404);
        $this->ensureEnabled();

        $page = $listing->comments()->with('user')->latest('id')->paginate(self::PER_PAGE);

        return response()->json([
            'data' => $page->getCollection()->map(fn (ListingComment $c) => $this->present($c->setRelation('listing', $listing)))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            // Formda "… olarak yorum yapıyorsunuz" için
            'viewer_name' => $this->authorName($request),
        ]);
    }

    public function store(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->status === ListingStatus::Approved, 404);
        $this->ensureEnabled();

        $user = $request->user('sanctum');

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:1000'],
        ], [
            'body.required' => 'Yorumunuzu yazın.',
            'body.min' => 'Yorum çok kısa.',
            'body.max' => 'Yorum en fazla 1000 karakter olabilir.',
        ]);

        // Gizli alan: insanlar görmez, robotlar doldurur
        if (filled($request->input('website'))) {
            throw ValidationException::withMessages(['body' => 'Yorum gönderilemedi.']);
        }

        $comment = $listing->comments()->create([
            'name' => $this->authorName($request),
            'body' => trim($data['body']),
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
        ]);
        $comment->setRelation('listing', $listing)->setRelation('user', $user);

        $this->notifyFollowers($comment, $listing);

        return response()->json($this->present($comment), 201);
    }

    /** Admin panelden (Site Ayarları / İlan Yorumları) kapatılabilir; yorumlar silinmez */
    private function ensureEnabled(): void
    {
        if (! SiteSetting::commentsEnabled()) {
            throw new HttpResponseException(response()->json(['message' => 'İlan yorumları şu anda kapalı.'], 403));
        }
    }

    /**
     * Yeni yorumu ilan sahibine ve ilana daha önce üye olarak yorum yazanlara mail ile bildirir
     * (yorumu yazan hariç; misafirlerin e-postası yok). Gönderim yanıttan sonra yapılır.
     */
    private function notifyFollowers(ListingComment $comment, Listing $listing): void
    {
        $authorId = $comment->user_id;
        $owner = $listing->user;

        $participants = User::query()
            ->whereIn('id', $listing->comments()->whereNotNull('user_id')->whereKeyNot($comment->id)->select('user_id'))
            ->whereKeyNot(array_filter([$authorId, $owner?->id]))
            ->get();

        MailSettings::sendLater(function () use ($comment, $owner, $authorId, $participants) {
            if ($owner && $owner->id !== $authorId) {
                $owner->notify(new ListingCommentedNotification($comment, toOwner: true));
            }
            foreach ($participants as $user) {
                $user->notify(new ListingCommentedNotification($comment, toOwner: false));
            }
        });
    }

    /** Üye: hesaptaki ad (başkası adına yazılamaz). Misafir: IP'ye bağlı sabit ad, örn. "Misafir04217". */
    private function authorName(Request $request): string
    {
        return $request->user('sanctum')?->fullName() ?? GuestName::for($request->ip());
    }

    private function present(ListingComment $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'body' => $c->body,
            'is_owner' => $c->isByListingOwner(),
            // Üye yorumunda profil fotoğrafı (misafirde ve fotoğrafı olmayan üyede null → baş harf)
            'avatar_url' => $c->user?->avatarUrl(),
            'created_at' => $c->created_at->toIso8601String(),
        ];
    }
}
