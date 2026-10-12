<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Notifications\ListingApprovedNotification;
use App\Notifications\ListingRejectedNotification;
use App\Support\MailSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Listing extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'category', 'product', 'amount', 'unit', 'price', 'price_negotiable',
        'available_on', 'description', 'city_id', 'district_id', 'show_phone',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'type' => ListingType::class,
            'status' => ListingStatus::class,
            'amount' => 'decimal:2',
            'price' => 'decimal:2',
            'price_negotiable' => 'boolean',
            'show_phone' => 'boolean',
            'available_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Fotoğraf kayıtları DB'de cascade ile silinir; dosyaları da temizle
        static::deleting(fn (Listing $listing) => Storage::disk('public')->deleteDirectory("listings/{$listing->id}"));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ListingPhoto::class)->orderBy('sort_order');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', ListingStatus::Approved);
    }

    /** Örn. "5 ton Domates" */
    public function title(): string
    {
        $amount = rtrim(rtrim(number_format((float) $this->amount, 2, ',', '.'), '0'), ',');

        return "{$amount} {$this->unit} {$this->product}";
    }

    public function approve(User $admin): void
    {
        $wasApproved = $this->status === ListingStatus::Approved;

        $this->forceFill([
            'status' => ListingStatus::Approved,
            'rejection_reason' => null,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
        ])->save();

        // Yeni yayına giren ilan için sahibine bilgi maili (zaten yayındaysa tekrar gitmez)
        if (! $wasApproved && $owner = $this->user) {
            MailSettings::sendLater(fn () => $owner->notify(new ListingApprovedNotification($this)));
        }
    }

    /** Sahibi içeriği değiştirdi: inceleme sıfırlanır, admin tekrar onaylar (kaydetmez) */
    public function resubmit(): void
    {
        $this->forceFill([
            'status' => ListingStatus::Pending,
            'rejection_reason' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ]);
    }

    public function reject(User $admin, string $reason): void
    {
        $alreadySent = $this->status === ListingStatus::Rejected && $this->rejection_reason === $reason;

        $this->forceFill([
            'status' => ListingStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
        ])->save();

        // Sahibine red sebebiyle bilgi maili (aynı sebeple tekrar reddedilirse ikinci kez gitmez)
        if (! $alreadySent && $owner = $this->user) {
            MailSettings::sendLater(fn () => $owner->notify(new ListingRejectedNotification($this)));
        }
    }
}
