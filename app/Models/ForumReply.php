<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Forum konusuna yazılan cevap */
class ForumReply extends Model
{
    protected $fillable = ['body'];

    protected static function booted(): void
    {
        // Konudaki cevap sayısı ve "son hareket" güncel kalsın (panelden silinince de)
        static::created(fn (ForumReply $reply) => $reply->topic?->refreshStats());
        static::deleted(fn (ForumReply $reply) => $reply->topic?->refreshStats());
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'forum_topic_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Görünen ad: üyenin hesap adı ya da misafirin IP'ye bağlı adı ("Misafir04217") */
    public function authorName(): string
    {
        return $this->user?->fullName() ?? $this->author_name ?? 'Misafir';
    }
}
