<?php

namespace App\Models;

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
