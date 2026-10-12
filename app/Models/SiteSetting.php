<?php

namespace App\Models;

use App\Models\Concerns\FlushesSiteCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Site genelindeki ayarlar (tek satır). Alanlar `data` JSON'unda tutulur:
 * site_name, logo, favicon, phone, email, address, social.*, seo.* ...
 */
class SiteSetting extends Model
{
    use FlushesSiteCache;

    protected $fillable = ['data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['data' => []]);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return data_get(static::current()->data, $key, $default);
    }

    /** Üyeler arası site içi mesajlaşma açık mı (panelden; ayar hiç kaydedilmediyse açık) */
    public static function messagingEnabled(): bool
    {
        return (bool) static::get('messaging_enabled', true);
    }
}
