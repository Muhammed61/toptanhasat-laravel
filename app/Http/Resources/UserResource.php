<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\TcKimlik;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_admin' => $this->is_admin,
            'avatar_url' => $this->avatarUrl(),
            // TC'nin kendisi siteye hiç gönderilmez
            'has_tc' => $this->hasTc(),
            'tc_masked' => TcKimlik::mask($this->tc_kimlik),
            'cks' => [
                'status' => $this->cks_status?->value,
                'number' => $this->cks_number,
                'rejection_reason' => $this->cks_rejection_reason,
                'submitted_at' => $this->cks_submitted_at?->toIso8601String(),
            ],
        ];
    }
}
