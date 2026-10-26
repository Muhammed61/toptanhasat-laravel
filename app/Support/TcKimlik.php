<?php

namespace App\Support;

/**
 * TC kimlik numarası. NVİ'nin ücretsiz doğrulama servisi 30.09.2025'te kapandığı için numara
 * nüfus kaydına sorulmaz; kontrol haneleri denetlenir (uydurma numaralar geçmez) ve bir numarayla
 * tek hesap açılır. Satıcıların kimliği, TC'nin de yazdığı ÇKS belgesinin panelde onaylanmasıyla doğrulanır.
 */
class TcKimlik
{
    /** 11 hane, ilk hane 0 değil, son iki hane kontrol hanesi */
    public static function isValid(string $tc): bool
    {
        if (! preg_match('/^[1-9]\d{10}$/', $tc)) {
            return false;
        }

        $d = array_map('intval', str_split($tc));

        return $d[9] === self::tenthDigit($d) && $d[10] === array_sum(array_slice($d, 0, 10)) % 10;
    }

    /** Aynı numarayla ikinci hesap açılmasın diye aranabilir tek yönlü özet (numara şifreli saklanır) */
    public static function hash(string $tc): string
    {
        return hash_hmac('sha256', $tc, (string) config('app.key'));
    }

    /** Panelde ve profilde: 123******78 */
    public static function mask(?string $tc): ?string
    {
        return $tc ? substr($tc, 0, 3).'******'.substr($tc, -2) : null;
    }

    /** Fabrika ve testler için kontrol haneleri doğru rastgele numara */
    public static function random(): string
    {
        $d = [random_int(1, 9)];
        for ($i = 1; $i < 9; $i++) {
            $d[] = random_int(0, 9);
        }
        $d[] = self::tenthDigit($d);
        $d[] = array_sum($d) % 10;

        return implode('', $d);
    }

    /** @param  list<int>  $d */
    private static function tenthDigit(array $d): int
    {
        return ((($d[0] + $d[2] + $d[4] + $d[6] + $d[8]) * 7 - ($d[1] + $d[3] + $d[5] + $d[7])) % 10 + 10) % 10;
    }
}
