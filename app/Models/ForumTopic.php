<?php

namespace App\Models;

use App\Models\Concerns\HasForumAuthor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Forum konusu: bir kategoride üyenin ya da misafirin açtığı başlık. Konunun içinde alt başlıklar
 * açılabilir (parent_id; tek seviye). Alt başlıktaki hareket üst konuyu da listede yukarı taşır.
 */
class ForumTopic extends Model
{
    use HasForumAuthor;

    protected $fillable = ['category_id', 'parent_id', 'title', 'body'];

    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (ForumTopic $topic) => $topic->last_activity_at ??= now());
        static::created(fn (ForumTopic $topic) => $topic->parent?->refreshStats());
        static::deleted(fn (ForumTopic $topic) => $topic->parent?->refreshStats());
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subtopics(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
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

    /** Cevap / alt başlık sayısı ve son hareket zamanı; alt başlıktaysa üst konu da güncellenir */
    public function refreshStats(): void
    {
        $times = array_filter([
            $this->created_at,
            $this->replies()->latest('id')->first()?->created_at,
            ($sub = $this->subtopics()->max('last_activity_at')) ? Carbon::parse($sub) : null,
        ]);

        $this->forceFill([
            'replies_count' => $this->replies()->count(),
            'subtopics_count' => $this->subtopics()->count(),
            'last_activity_at' => max($times),
        ])->saveQuietly();

        $this->parent?->refreshStats();
    }
}
