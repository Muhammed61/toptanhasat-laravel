<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

/**
 * Site haritası verisi: web sitesi (Nuxt) /sitemap.xml'i bununla üretir.
 * Yalnızca herkese açık sayfalar ve yayındaki (onaylı) ilanlar.
 */
class SitemapController extends Controller
{
    /** Panel sayfası → sitedeki adresi (giriş sayfası arama sonuçlarında gereksiz) */
    public const PAGE_PATHS = [
        'home' => '/',
        'about' => '/about',
        'how-it-works' => '/how-it-works',
        'contact' => '/contact',
        'kvkk' => '/kvkk',
        'terms' => '/terms',
        'cookie' => '/cookie',
    ];

    /** Google'ın tek site haritası dosyası sınırı */
    private const MAX_LISTINGS = 49_000;

    public function __invoke(): JsonResponse
    {
        $pages = Page::whereIn('slug', array_keys(self::PAGE_PATHS))
            ->get(['slug', 'updated_at'])
            ->map(fn (Page $page) => [
                'path' => self::PAGE_PATHS[$page->slug],
                'updated_at' => $page->updated_at?->toIso8601String(),
            ])
            ->sortBy(fn (array $page) => array_search($page['path'], array_values(self::PAGE_PATHS)))
            ->values();

        $listings = Listing::approved()
            ->latest('updated_at')
            ->limit(self::MAX_LISTINGS)
            ->get(['id', 'updated_at'])
            ->map(fn (Listing $listing) => [
                'path' => "/ilan/{$listing->id}",
                'updated_at' => $listing->updated_at?->toIso8601String(),
            ]);

        return response()->json(['pages' => $pages, 'listings' => $listings]);
    }
}
