<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\CksStatus;
use App\Notifications\CksReviewedNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Support\MailSettings;
use App\Support\TcKimlik;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     * is_admin bilerek yok: sadece `php artisan admin:create` ile verilir.
     * TC ve ÇKS alanları da yok: setTcKimlik() / submitCks() / approveCks() ile yazılır.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'avatar_path',
        'password',
        'terms_accepted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $attributes = [
        'is_admin' => false,
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'tc_kimlik',
        'tc_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            // TC kimlik numarası veritabanında şifreli durur
            'tc_kimlik' => 'encrypted',
            'cks_status' => CksStatus::class,
            'cks_submitted_at' => 'datetime',
            'cks_reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // İlanları model üzerinden sil ki fotoğraf dosyaları da temizlensin
        static::deleting(fn (User $user) => $user->listings()->each(fn (Listing $listing) => $listing->delete()));

        // Profil fotoğrafı değişince ya da üye silinince eski dosyayı kaldır
        static::updated(function (User $user) {
            if ($user->wasChanged('avatar_path') && $old = $user->getOriginal('avatar_path')) {
                Storage::disk('public')->delete($old);
            }
            if ($user->wasChanged('cks_document_path') && $old = $user->getOriginal('cks_document_path')) {
                Storage::disk('documents')->delete($old);
            }

            // Şifre nereden değişirse değişsin hesap sahibine bilgi maili
            if ($user->wasChanged('password')) {
                $changedAt = now();
                MailSettings::sendLater(fn () => $user->notify(new PasswordChangedNotification($changedAt)));
            }
        });
        static::deleted(function (User $user) {
            $user->avatar_path && Storage::disk('public')->delete($user->avatar_path);
            $user->cks_document_path && Storage::disk('documents')->delete($user->cks_document_path);
        });
    }

    /** Yüklenmiş profil fotoğrafı; yoksa null (panel baş harf avatarı gösterir) */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatarUrl();
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /** Kayıtta ya da sonradan girilen TC (şifreli + tekrar kontrolü için özet) */
    public function setTcKimlik(string $tc): static
    {
        return $this->forceFill(['tc_kimlik' => $tc, 'tc_hash' => TcKimlik::hash($tc)]);
    }

    public function hasTc(): bool
    {
        return $this->tc_hash !== null;
    }

    public static function tcTaken(string $tc): bool
    {
        return static::query()->where('tc_hash', TcKimlik::hash($tc))->exists();
    }

    /** Satılık ilan verebilir: ÇKS belgesi gönderilmiş (onay bekliyor ya da onaylı) */
    public function canSubmitSellListing(): bool
    {
        return $this->is_admin || in_array($this->cks_status, [CksStatus::Pending, CksStatus::Approved], true);
    }

    /** Satılık ilanı yayına alınabilir: ÇKS onaylı */
    public function canPublishSellListing(): bool
    {
        return $this->is_admin || $this->cks_status === CksStatus::Approved;
    }

    /** Üye ÇKS numarasını ve belgesini gönderdi; yönetici onayını bekler (eski belge silinir) */
    public function submitCks(string $number, string $documentPath): void
    {
        $this->forceFill([
            'cks_number' => $number,
            'cks_document_path' => $documentPath,
            'cks_status' => CksStatus::Pending,
            'cks_rejection_reason' => null,
            'cks_submitted_at' => now(),
            'cks_reviewed_at' => null,
        ])->save();
    }

    public function approveCks(): void
    {
        $this->forceFill(['cks_status' => CksStatus::Approved, 'cks_rejection_reason' => null, 'cks_reviewed_at' => now()])->save();
        MailSettings::sendLater(fn () => $this->notify(new CksReviewedNotification(approved: true)));
    }

    public function rejectCks(string $reason): void
    {
        $this->forceFill(['cks_status' => CksStatus::Rejected, 'cks_rejection_reason' => $reason, 'cks_reviewed_at' => now()])->save();
        MailSettings::sendLater(fn () => $this->notify(new CksReviewedNotification(approved: false, reason: $reason)));
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /** /admin paneline sadece adminler girebilir */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin;
    }

    public function getFilamentName(): string
    {
        return $this->fullName();
    }

    /** Türkçe, Vue sitesindeki sıfırlama sayfasına giden mail */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
