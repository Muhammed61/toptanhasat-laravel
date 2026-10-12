<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Bir ilan üzerinden alıcı (buyer, konuşmayı başlatan) ile satıcı (seller, ilan sahibi)
 * arasındaki konuşma. Okunmamış durumu her taraf için son okunan mesaj id'siyle tutulur:
 * last_message_id > *_read_id ise karşı taraftan okunmamış mesaj vardır.
 */
class Conversation extends Model
{
    protected $fillable = ['listing_id', 'listing_title', 'buyer_id', 'seller_id'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id));
    }

    /** Kullanıcının okumadığı (karşı taraftan gelen) mesaj içeren konuşmalar */
    public function scopeUnreadFor(Builder $query, User $user): void
    {
        $query->whereNotNull('last_message_id')->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->where('buyer_id', $user->id)
                ->where(fn (Builder $q) => $q->whereNull('buyer_read_id')->orWhereColumn('last_message_id', '>', 'buyer_read_id')))
            ->orWhere(fn (Builder $q) => $q->where('seller_id', $user->id)
                ->where(fn (Builder $q) => $q->whereNull('seller_read_id')->orWhereColumn('last_message_id', '>', 'seller_read_id'))));
    }

    public function hasParticipant(User $user): bool
    {
        return $user->id === $this->buyer_id || $user->id === $this->seller_id;
    }

    public function roleOf(User $user): string
    {
        return $user->id === $this->seller_id ? 'seller' : 'buyer';
    }

    public function otherParty(User $user): User
    {
        return $user->id === $this->seller_id ? $this->buyer : $this->seller;
    }

    public function isUnreadFor(User $user): bool
    {
        $readId = $this->{$this->roleOf($user).'_read_id'};

        return $this->last_message_id !== null && ($readId === null || $this->last_message_id > $readId);
    }

    /** Şu ana kadarki mesajları okundu say */
    public function markReadBy(User $user): void
    {
        $this->forceFill([$this->roleOf($user).'_read_id' => $this->last_message_id])->save();
    }

    /** Mesaj ekler; gönderen kendi mesajını okumuş sayılır */
    public function addMessage(User $sender, string $body): Message
    {
        $message = $this->messages()->create(['sender_id' => $sender->id, 'body' => $body]);

        $this->forceFill([
            'last_message_at' => $message->created_at,
            'last_message_id' => $message->id,
            $this->roleOf($sender).'_read_id' => $message->id,
        ])->save();

        return $message;
    }

    /** Admin tarafından mesaj silinir; son mesaj bilgisi kalanlara göre güncellenir */
    public function removeMessage(Message $message): void
    {
        $message->delete();

        $last = $this->messages()->latest('id')->first();

        $this->forceFill([
            'last_message_at' => $last?->created_at,
            'last_message_id' => $last?->id,
        ])->save();
    }

    /** İlan silinmişse konuşma başlarken kaydedilen başlık */
    public function listingTitle(): string
    {
        return $this->listing?->title() ?? $this->listing_title;
    }
}
