<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingComment;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\ListingCommentedNotification;
use App\Rules\NoLinks;
use App\Support\GuestName;
use App\Support\MailSettings;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * İlan detayındaki yorumlar: herkes okur ve yazar (üyelik/onay yok). Misafirler IP'ye bağlı
 * "Misafir04217" gibi bir adla görünür. Kötüye kullanıma (reklam, backlink) karşı:
 *  - bağlantı / alan adı / e-posta içeren yorum kabul edilmez (NoLinks)
 *  - IP başına dakikada 5 yorum (route), misafirler için günde GUEST_DAILY_LIMIT yorum
 *  - aynı kişi aynı ilana aynı yorumu tekrar gönderemez
 *  - robotların doldurduğu gizli "website" alanı; admin panelden silinir
 */
class ListingCommentController extends Controller
{
    public const PER_PAGE = 20;

    /** Ana sayfadaki "Son yorumlar" bölümünde gösterilen yorum sayısı */
    public const LATEST_LIMIT = 6;

    /** Misafir (üye olmayan) bir IP'den 24 saatte yazılabilecek en fazla yorum */
    public const GUEST_DAILY_LIMIT = 20;

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

    /**
     * Ana sayfadaki "Son yorumlar": yayındaki ilanlara yazılan en yeni yorumlar ve hangi ilana
     * yazıldıkları. Yorumlar kapalıysa boş liste (bölüm gizlenir).
     */
    public function latest(): JsonResponse
    {
        if (! SiteSetting::commentsEnabled()) {
            return response()->json(['data' => []]);
        }

        $comments = ListingComment::query()
            ->whereHas('listing', fn ($q) => $q->approved())
            ->with(['user', 'listing.city', 'listing.district'])
            ->latest('id')
            ->limit(self::LATEST_LIMIT)
            ->get();

        return response()->json([
            'data' => $comments->map(fn (ListingComment $c) => [
                ...$this->present($c),
                'body' => Str::limit($c->body, 160),
                'listing' => [
                    'id' => $c->listing->id,
                    'title' => $c->listing->title(),
                    'location' => "{$c->listing->district?->name}, {$c->listing->city?->name}",
                ],
            ])->values(),
        ]);
    }

    public function store(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->status === ListingStatus::Approved, 404);
        $this->ensureEnabled();

        $user = $request->user('sanctum');

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:1000', new NoLinks],
        ], [
            'body.required' => 'Yorumunuzu yazın.',
            'body.min' => 'Yorum çok kısa.',
            'body.max' => 'Yorum en fazla 1000 karakter olabilir.',
        ]);

        // Gizli alan: insanlar görmez, robotlar doldurur
        if (filled($request->input('website'))) {
            throw ValidationException::withMessages(['body' => 'Yorum gönderilemedi.']);
        }

        $this->ensureNotFlooding($request, $listing, trim($data['body']), $user);

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

    /** Aynı yorumu tekrar gönderme ve misafirler için günlük sınır */
    private function ensureNotFlooding(Request $request, Listing $listing, string $body, ?User $user): void
    {
        $author = fn ($q) => $user ? $q->where('user_id', $user->id) : $q->whereNull('user_id')->where('ip_address', $request->ip());
        $since = now()->subDay();

        $duplicate = $listing->comments()->where($author)->where('created_at', '>=', $since)
            ->pluck('body')
            ->contains(fn (string $old) => self::normalize($old) === self::normalize($body));
        if ($duplicate) {
            throw ValidationException::withMessages(['body' => 'Bu yorumu zaten gönderdiniz.']);
        }

        if (! $user && ListingComment::whereNull('user_id')->where('ip_address', $request->ip())->where('created_at', '>=', $since)->count() >= self::GUEST_DAILY_LIMIT) {
            // Form hatası olarak döner; sitede yorum kutusunun altında görünür
            throw ValidationException::withMessages([
                'body' => 'Bugün çok fazla yorum yazdınız. Lütfen yarın tekrar deneyin ya da üye olun.',
            ]);
        }
    }

    /** Karşılaştırma için: Türkçe küçük harf (İ→i, I→ı), fazla boşluklar tek boşluk */
    private static function normalize(string $text): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], trim($text))));
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
