<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * İlan detayındaki yorumlar: herkes okur ve yazar (üyelik/onay yok). Kötüye kullanıma karşı
 * IP başına hız sınırı (route) ve robotların doldurduğu gizli "website" alanı; admin panelden silinir.
 */
class ListingCommentController extends Controller
{
    public const PER_PAGE = 20;

    public function index(Listing $listing): JsonResponse
    {
        abort_unless($listing->status === ListingStatus::Approved, 404);

        $page = $listing->comments()->latest('id')->paginate(self::PER_PAGE);

        return response()->json([
            'data' => $page->getCollection()->map(fn (ListingComment $c) => $this->present($c->setRelation('listing', $listing)))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function store(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->status === ListingStatus::Approved, 404);

        $user = $request->user('sanctum');

        $data = $request->validate([
            'name' => [$user ? 'nullable' : 'required', 'string', 'max:60'],
            'body' => ['required', 'string', 'min:2', 'max:1000'],
        ], [
            'name.required' => 'Adınızı yazın.',
            'name.max' => 'Ad en fazla 60 karakter olabilir.',
            'body.required' => 'Yorumunuzu yazın.',
            'body.min' => 'Yorum çok kısa.',
            'body.max' => 'Yorum en fazla 1000 karakter olabilir.',
        ]);

        // Gizli alan: insanlar görmez, robotlar doldurur
        if (filled($request->input('website'))) {
            throw ValidationException::withMessages(['body' => 'Yorum gönderilemedi.']);
        }

        $comment = $listing->comments()->create([
            // Üye yazıyorsa hesaptaki adı kullanılır (başkası adına yazılamasın)
            'name' => $user ? $user->fullName() : trim($data['name']),
            'body' => trim($data['body']),
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json($this->present($comment->setRelation('listing', $listing)), 201);
    }

    private function present(ListingComment $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'body' => $c->body,
            'is_owner' => $c->isByListingOwner(),
            'created_at' => $c->created_at->toIso8601String(),
        ];
    }
}
