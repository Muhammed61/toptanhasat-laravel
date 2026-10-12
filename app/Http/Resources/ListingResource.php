<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Listing */
class ListingResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
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
            'city' => ['id' => $this->city_id, 'name' => $this->whenLoaded('city', fn () => $this->city->name)],
            'district' => ['id' => $this->district_id, 'name' => $this->whenLoaded('district', fn () => $this->district->name)],
            'show_phone' => $this->show_phone,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
