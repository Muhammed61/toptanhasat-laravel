<?php

namespace App\Http\Resources;

use App\Enums\ListingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Herkese açık ilan görünümü. Durum/red sebebi ve iletişim bilgisi İÇERMEZ;
 * telefon/e-posta yalnızca üyelere ayrı uçtan (/listings/{id}/contact) verilir.
 *
 * @mixin \App\Models\Listing
 */
class PublicListingResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $owner = $this->user;

        return [
            'id' => $this->id,
            'title' => $this->title(),
            'type' => $this->type->value,
            'category' => $this->category,
            'product' => $this->product,
            'amount' => (float) $this->amount,
            'unit' => $this->unit,
            'price' => $this->price === null ? null : (float) $this->price,
            'price_negotiable' => $this->price_negotiable,
            'available_on' => $this->available_on?->toDateString(),
            'description' => $this->description,
            'city' => ['id' => $this->city_id, 'name' => $this->city->name],
            'district' => ['id' => $this->district_id, 'name' => $this->district->name],
            // "Ahmet Ç." — tam ad yalnızca üyelere iletişim bilgisiyle birlikte gösterilir
            'owner' => [
                'name' => $owner->first_name.' '.Str::upper(mb_substr($owner->last_name, 0, 1)).'.',
                'member_since' => $owner->created_at->toDateString(),
            ],
            'is_own' => $request->user('sanctum')?->id === $this->user_id,
            // Sahibi/admin onaysız ilanı önizlerken uyarı göstermek için
            'is_published' => $this->status === ListingStatus::Approved,
            // Numaranın kendisi değil, sadece paylaşılıp paylaşılmadığı (buton gösterimi için)
            'has_phone' => $this->show_phone,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
