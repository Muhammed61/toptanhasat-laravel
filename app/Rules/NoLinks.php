<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Metinde bağlantı, alan adı veya e-posta adresi olmasın (yorumlara reklam/backlink bırakılmasın).
 * Gizlenmiş yazımları da yakalar: "site . com", "site(nokta)com", "site dot com", "hxxp://", "www site".
 * Cümle sonları takılmaz: "Ürün taze. Su ile yıkandı" (noktadan önce boşluk yoksa arkasında da olmamalı).
 */
class NoLinks implements ValidationRule
{
    /** Sık kullanılan uzantılar; "1.5 ton" gibi sayılar takılmaz */
    private const TLDS = 'com|net|org|info|biz|xyz|io|co|me|tr|ru|de|uk|eu|us|online|site|website|top|shop|store|link|click|ly|gl|to|cc|tk|ml|ga|cf|gq|app|dev|pro|club|live|vip|bet|casino|win|life|world|space|fun|icu|cyou|buzz|today|news|blog|page|tv|in|ws|su';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::containsLink((string) $value)) {
            $fail('Yorumlarda bağlantı, web sitesi veya e-posta adresi paylaşılamaz.');
        }
    }

    public static function containsLink(string $text): bool
    {
        $text = mb_strtolower($text);
        // "." bitişik ya da önünde boşlukla; "(nokta)", "[dot]", " nokta ", " dot "
        $dot = '(?:\.|\s+\.\s*|\s*[(\[]\s*(?:dot|nokta)\s*[)\]]\s*|\s+(?:dot|nokta)\s+)';

        $patterns = [
            '~h\s*[tx]\s*[tx]\s*p\s*s?\s*:~u',                     // http:, https:, hxxp:
            '~\bwww\s*[.\s]~u',                                    // www. / www site
            '~<\s*a\b|\[\s*url|\]\s*\(~u',                         // HTML, BBCode, Markdown bağlantıları
            '~[\p{L}0-9._%+-]+\s*@\s*[\p{L}0-9-]+'.$dot.'[a-z]{2,}~u', // e-posta
            '~\b[\p{L}0-9-]{2,}'.$dot.'(?:'.self::TLDS.')\b~u',    // alan.com, alan . com, alan(nokta)com
            '~\b(?:t\.me|wa\.me|bit\.ly|tinyurl|goo\.gl|cutt\.ly|shorturl)\b~u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }
}
