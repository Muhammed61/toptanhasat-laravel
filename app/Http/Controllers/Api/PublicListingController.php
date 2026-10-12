<?php

namespace App\Http\Controllers\Api;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicListingResource;
use App\Models\Listing;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Yayındaki (onaylı) ilanların herkese açık listesi ve detayı */
class PublicListingController extends Controller
{
    /** Ana sayfadaki miktar seçenekleri, kg cinsinden [en az, en fazla] */
    public const AMOUNT_RANGES = [
        '500kg-1ton' => [500, 1000],
        '1-5ton' => [1000, 5000],
        '5-10ton' => [5000, 10000],
        '10ton+' => [10000, null],
    ];

    public const PER_PAGE = 20;

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(ListingType::class)],
            'category' => ['nullable', 'string', 'max:30'],
            'product' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'integer'],
            'district' => ['nullable', 'integer'],
            'amount' => ['nullable', Rule::in(array_keys(self::AMOUNT_RANGES))],
        ]);

        $listings = Listing::approved()
            ->with(['city', 'district', 'user'])
            ->when($filters['type'] ?? null, fn (Builder $q, $v) => $q->where('type', $v))
            ->when($filters['category'] ?? null, fn (Builder $q, $v) => $q->where('category', $v))
            ->when($filters['product'] ?? null, fn (Builder $q, $v) => $q->where('product', $v))
            ->when($filters['city'] ?? null, fn (Builder $q, $v) => $q->where('city_id', $v))
            ->when($filters['district'] ?? null, fn (Builder $q, $v) => $q->where('district_id', $v))
            ->when($filters['amount'] ?? null, function (Builder $q, $key) {
                // İlanlar ton ya da kg girilebildiği için kg'ye çevirerek karşılaştır
                [$min, $max] = self::AMOUNT_RANGES[$key];
                $kg = "(CASE WHEN unit = 'ton' THEN amount * 1000 ELSE amount END)";
                $q->whereRaw("$kg >= ?", [$min]);
                if ($max !== null) {
                    $q->whereRaw("$kg <= ?", [$max]);
                }
            })
            ->latest()
            ->latest('id') // aynı saniyede eklenenlerde de en yeni önce
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return PublicListingResource::collection($listings);
    }

    public function show(Request $request, Listing $listing): PublicListingResource
    {
        // Onaysız ilanı yalnızca sahibi ve adminler görebilir
        $viewer = $request->user('sanctum');
        abort_unless(
            $listing->status === ListingStatus::Approved || $viewer?->id === $listing->user_id || $viewer?->is_admin,
            404,
        );

        return new PublicListingResource($listing->load(['city', 'district', 'user']));
    }

    /**
     * İlan sahibinin iletişim bilgisi — sadece üyelere.
     * Sadece "telefonum ilanda görünsün" seçilmiş ilanlarda; diğerlerinde site içi mesajlaşma.
     */
    public function contact(Request $request, Listing $listing): JsonResponse
    {
        // Telefonunu paylaşmayan ilan sahibine yalnızca site içi mesajla ulaşılır
        abort_unless($listing->status === ListingStatus::Approved && $listing->show_phone, 404);

        $owner = $listing->user;

        return response()->json([
            'name' => $owner->fullName(),
            'phone' => Phone::display($owner->phone),
            'phone_href' => 'tel:+90'.$owner->phone,
        ]);
    }
}
