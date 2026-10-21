<?php

namespace App\Models;

use App\Models\Concerns\HasForumAuthor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Forum konusu: bir kategoride üyenin ya da misafirin açtığı başlık */
class ForumTopic extends Model
{
    use HasForumAuthor;

    protected $fillable = ['category_id', 'title', 'body'];

    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (ForumTopic $topic) => $topic->last_activity_at ??= now());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ForumReply::class)->orderBy('id');
    }

    /** Cevap sayısı ve son hareket zamanı (cevap eklenince / silinince) */
    public function refreshStats(): void
    {
        $last = $this->replies()->latest('id')->first();

        $this->forceFill([
            'replies_count' => $this->replies()->count(),
            'last_activity_at' => $last?->created_at ?? $this->created_at,
        ])->saveQuietly();
    }
}
