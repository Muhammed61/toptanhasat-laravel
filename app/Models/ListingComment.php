<?php

namespace App\Models;

use App\Support\AdminNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** İlan detayındaki herkese açık yorum */
class ListingComment extends Model
{
    protected $fillable = ['name', 'body', 'user_id', 'ip_address'];

    protected static function booted(): void
    {
        // Yönetim paneli bildirimi (zil); bkz. AdminNotifier
        // Bildirim hatası yorumu/mesajı engellemesin (hata loglanır)
        static::created(fn (ListingComment $model) => rescue(fn () => AdminNotifier::comment($model)));
    }

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
