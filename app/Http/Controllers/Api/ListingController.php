<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListingController extends Controller
{
    /** Yeni ilan: her zaman "Onay bekliyor" durumunda başlar, admin onaylayınca yayına girer. */
    public function store(StoreListingRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['accept_responsibility']);
        if ($request->boolean('price_negotiable')) {
            $data['price'] = null;
        }

        $listing = $request->user()->listings()->create($data);
        $listing->load(['city', 'district']);

        return (new ListingResource($listing))->response()->setStatusCode(201);
    }

    /** Düzenleme formu için kendi ilanı (her durumda) */
    public function showOwn(Request $request, Listing $listing): ListingResource
    {
        abort_unless($listing->user_id === $request->user()->id, 404);

        return new ListingResource($listing->load(['city', 'district']));
    }

    /**
     * İlan sahibi düzenler. İçerik değiştiyse ilan tekrar onaya düşer (onaylı ilan
     * sonradan kontrolsüz değiştirilemesin); sadece telefon görünürlüğü değiştiyse durum korunur.
     */
    public function update(StoreListingRequest $request, Listing $listing): ListingResource
    {
        abort_unless($listing->user_id === $request->user()->id, 404);

        $data = $request->safe()->except(['accept_responsibility']);
        if ($request->boolean('price_negotiable')) {
            $data['price'] = null;
        }

        $listing->fill($data);
        if ($listing->isDirty(array_diff($listing->getFillable(), ['show_phone']))) {
            $listing->resubmit();
        }
        $listing->save();

        return new ListingResource($listing->load(['city', 'district']));
    }

    /** Kullanıcının kendi ilanları (tüm durumlar) */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $listings = $request->user()->listings()
            ->with(['city', 'district'])
            ->latest()
            ->get();

        return ListingResource::collection($listings);
    }
}
