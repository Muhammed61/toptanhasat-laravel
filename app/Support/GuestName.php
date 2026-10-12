<?php

namespace App\Support;

/**
 * Üye olmayan yorumcular için IP'ye bağlı sabit takma ad: "Misafir04217".
 * Aynı IP her zaman aynı adı alır; numara uygulama anahtarıyla türetildiği için
 * addan IP adresi geri çıkarılamaz.
 */
class GuestName
{
    public static function for(?string $ip): string
    {
        $hash = hash_hmac('sha256', (string) $ip, (string) config('app.key'));
        $number = hexdec(substr($hash, 0, 8)) % 100000;

        return 'Misafir'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }
}
