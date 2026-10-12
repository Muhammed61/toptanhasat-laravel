<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
        });
        static::deleted(fn (User $user) => $user->avatar_path && Storage::disk('public')->delete($user->avatar_path));
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
}
