<?php

namespace App\Models;

use App\Support\AdminNotifier;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    public const SUBJECTS = [
        'general' => 'Genel Bilgi',
        'support' => 'Teknik Destek',
        'listing' => 'İlan ve Üyelik',
        'partnership' => 'İş Birliği',
        'other' => 'Diğer',
    ];

    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'ip_address'];

    protected static function booted(): void
    {
        // Yönetim paneli bildirimi (zil); bkz. AdminNotifier
        // Bildirim hatası yorumu/mesajı engellemesin (hata loglanır)
        static::created(fn (ContactMessage $model) => rescue(fn () => AdminNotifier::contact($model)));
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function subjectLabel(): string
    {
        return self::SUBJECTS[$this->subject] ?? $this->subject;
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}
