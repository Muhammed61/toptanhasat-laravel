<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Konuşma özeti, bakan kullanıcıya göre: karşı taraf, kendi rolü, okunmamış durumu.
 *
 * @mixin \App\Models\Conversation
 */
class ConversationResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $other = $this->otherParty($viewer);
        $listing = $this->listing;
        $last = $this->latestMessage;

        return [
            'id' => $this->id,
            'role' => $this->roleOf($viewer), // bakan kişi: buyer | seller
            'listing' => [
                'id' => $listing?->id,
                'title' => $listing?->title() ?? $this->listing_title,
                'category' => $listing?->category,
                'available' => $listing !== null,
            ],
            'other' => [
                'name' => $other->fullName(),
                'avatar_url' => $other->avatarUrl(),
            ],
            'last_message' => $last ? [
                'body' => Str::limit($last->body, 80),
                'mine' => $last->sender_id === $viewer->id,
                'created_at' => $last->created_at->toIso8601String(),
            ] : null,
            'unread' => $this->isUnreadFor($viewer),
        ];
    }
}
