<?php

namespace App\Models;

use App\Support\AdminNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['sender_id', 'body'];

    protected static function booted(): void
    {
        // Yönetim paneli bildirimi (zil); bkz. AdminNotifier
        // Bildirim hatası yorumu/mesajı engellemesin (hata loglanır)
        static::created(fn (Message $model) => rescue(fn () => AdminNotifier::message($model)));
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
