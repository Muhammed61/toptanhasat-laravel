<?php

namespace App\Support;

class Phone
{
    /**
     * Türkiye cep numarasını 10 haneli "5XXXXXXXXX" biçimine çevirir.
     * "0555 123 45 67", "+90 555 123 4567", "5551234567" → "5551234567".
     * Geçersizse null döner.
     */
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^5\d{9}$/', $digits) ? $digits : null;
    }

    /** "5551234567" → "0555 123 45 67" (biçimlenemezse olduğu gibi döner) */
    public static function display(?string $value): ?string
    {
        return $value !== null && preg_match('/^(5\d{2})(\d{3})(\d{2})(\d{2})$/', $value, $m)
            ? "0{$m[1]} {$m[2]} {$m[3]} {$m[4]}"
            : $value;
    }
}
