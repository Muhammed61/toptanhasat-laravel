<?php

namespace App\Support;

use App\Models\Category;
use App\Models\MarketPrice;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Vue'nun açılışta tek seferde aldığı herkese açık site verisi (GET /api/site).
 * Ayar/katalog/fiyat değişince FlushesSiteCache ile önbellek temizlenir.
 */
class SiteData
{
    /**
     * Veri yapısı değişince (yeni alan eklenince) sürümü artırın; eski önbellek kendiliğinden
     * geçersiz olur, yayına alırken önbellek temizlemeyi unutmak sorun çıkarmaz.
     */
    private const VERSION = 2;

    private const CACHE_KEY = 'site.public.v'.self::VERSION;

    public static function get(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::build());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function build(): array
    {
        $s = SiteSetting::current()->data;
        $text = fn (string $key, string $default = '') => (string) (data_get($s, $key) ?: $default);

        return [
            'settings' => [
                'site_name' => $text('site_name', 'Toptanhasat'),
                'logo_url' => self::url(data_get($s, 'logo')),
                'logo_light_url' => self::url(data_get($s, 'logo_light')),
                'favicon_url' => self::url(data_get($s, 'favicon')),
                'messaging_enabled' => (bool) data_get($s, 'messaging_enabled', true),
                'comments_enabled' => (bool) data_get($s, 'comments_enabled', true),
                'footer_text' => $text('footer_text'),
                'footer_disclaimer' => $text('footer_disclaimer'),
                'copyright' => $text('copyright'),
                'phone' => $text('phone'),
                'email' => $text('email'),
                'address' => $text('address'),
                'working_hours' => $text('working_hours'),
                'social' => collect(['facebook', 'x', 'instagram', 'linkedin', 'youtube'])
                    ->mapWithKeys(fn ($k) => [$k => $text("social.$k")])
                    ->filter()
                    ->all(),
                'seo' => [
                    'default_title' => $text('seo.default_title', 'Toptanhasat'),
                    'title_suffix' => $text('seo.title_suffix'),
                    'description' => $text('seo.description'),
                    'og_image_url' => self::url(data_get($s, 'seo.og_image')),
                    'google_site_verification' => $text('seo.google_site_verification'),
                ],
            ],
            'categories' => Category::active()
                ->with(['products' => fn ($q) => $q->where('is_active', true)])
                ->get()
                ->map(fn (Category $c) => [
                    'key' => $c->slug,
                    'label' => $c->name,
                    'icon' => $c->icon,
                    'badge' => $c->badge,
                    'products' => $c->products->pluck('name')->values(),
                ])
                ->values(),
            'market_prices' => MarketPrice::where('is_active', true)
                ->orderBy('sort')
                ->get()
                ->map(fn (MarketPrice $p) => [
                    'product' => $p->product,
                    'price' => (float) $p->price,
                    'change' => (float) $p->change,
                ])
                ->values(),
        ];
    }

    private static function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
