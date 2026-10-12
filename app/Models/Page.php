<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sitedeki sabit sayfaların içeriği. Sayfalar seeder ile oluşur, panelden sadece düzenlenir.
 * `body` zengin metin (HTML), `content` sayfaya özel yapılandırılmış alanlar.
 */
class Page extends Model
{
    public const LABELS = [
        'home' => 'Ana Sayfa',
        'about' => 'Hakkımızda',
        'how-it-works' => 'Nasıl Çalışır?',
        'contact' => 'İletişim',
        'auth' => 'Giriş / Üye Ol',
        'kvkk' => 'KVKK Aydınlatma Metni',
        'terms' => 'Kullanım Koşulları',
        'cookie' => 'Çerez Politikası',
    ];

    /** Uzun metni zengin metin editörüyle düzenlenen sayfalar */
    public const RICH_TEXT = ['about', 'kvkk', 'terms', 'cookie'];

    protected $fillable = ['slug', 'title', 'subtitle', 'body', 'content', 'seo_title', 'seo_description'];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    public function label(): string
    {
        return self::LABELS[$this->slug] ?? $this->title;
    }
}
