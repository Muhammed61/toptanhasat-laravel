<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** İlan detayındaki herkese açık yorum */
class ListingComment extends Model
{
    protected $fillable = ['name', 'body', 'user_id', 'ip_address'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Yorumu ilan sahibi (giriş yapmışken) yazdıysa */
    public function isByListingOwner(): bool
    {
        return $this->user_id !== null && $this->user_id === $this->listing?->user_id;
    }
}
