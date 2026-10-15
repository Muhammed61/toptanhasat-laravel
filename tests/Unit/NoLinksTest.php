<?php

namespace Tests\Unit;

use App\Rules\NoLinks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NoLinksTest extends TestCase
{
    public static function links(): array
    {
        return [
            ['Detaylar için https://ornek.com adresine bakın'],
            ['hxxp://spam.xyz'],
            ['www.ucuzgubre.net'],
            ['www ucuzgubre net'],
            ['ucuzgubre.com.tr en uygun fiyatlar'],
            ['ucuzgubre . com'],
            ['ucuzgubre(nokta)com'],
            ['ucuzgubre [dot] net'],
            ['ucuzgubre nokta com'],
            ['Bana yazın: ali@gmail.com'],
            ['ali @ gmail . com'],
            ['<a href="x">tıkla</a>'],
            ['[url=x]tıkla[/url]'],
            ['[tıkla](ornek)'],
            ['Telegram: t.me/ucuzgubre'],
            ['bit.ly/abc'],
            ['BAHIS-SITESI.VIP'],
        ];
    }

    public static function normalText(): array
    {
        return [
            ['Teslimat Bursa\'ya olur mu?'],
            ['Ürün taze. Su ile yıkandı.'],
            ['1.5 ton domates var, fiyat 12,50 TL.'],
            ['Merhaba. De ki bana fiyatı ne?'],
            ['Kg fiyatı 25 TL. Toplam 3 ton.'],
            ['Saat 14.30 gibi arayın, nokta atışı teslimat.'],
            ['Organik mi? Tr standartlarına uygun mu?'],
            ['Fiyat nedir acaba'],
        ];
    }

    #[DataProvider('links')]
    public function test_links_are_detected(string $text): void
    {
        $this->assertTrue(NoLinks::containsLink($text), $text);
    }

    #[DataProvider('normalText')]
    public function test_normal_comments_are_allowed(string $text): void
    {
        $this->assertFalse(NoLinks::containsLink($text), $text);
    }
}
