<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Başlangıç reklam görselleri (database/seeders/banners). Yalnızca hiç görsel yokken eklenir;
 * panelde Reklam Görselleri'nden değiştirilir. Yayında: php artisan db:seed --class=BannerSeeder --force
 */
class BannerSeeder extends Seeder
{
    /** [dosya, başlık, bağlantı] */
    public const BANNERS = [
        ['ilan-ver.webp', 'Mahsülünü ücretsiz ilana çıkar, alıcılar sana ulaşsın', '/ilan-ver'],
        ['narenciye-sezonu.webp', 'Narenciye sezonu başladı: portakal, mandalina ve limon ilanları', '/?kategori=narenciye'],
        ['toptan-alici.webp', 'Toptan alıcı mısın? Bölgendeki üreticilerin satılık ilanlarını incele', '/?tur=satilik'],
    ];

    public function run(): void
    {
        if (Banner::query()->exists()) {
            return;
        }

        foreach (self::BANNERS as $i => [$file, $title, $url]) {
            $path = 'banners/'.$file;
            Storage::disk('public')->put($path, file_get_contents(__DIR__.'/banners/'.$file));

            Banner::create(['title' => $title, 'image_path' => $path, 'url' => $url, 'sort' => $i + 1]);
        }
    }
}
