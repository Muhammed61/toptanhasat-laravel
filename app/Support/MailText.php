<?php

namespace App\Support;

use Illuminate\Contracts\Support\Htmlable;

/**
 * Mail içeriğine giren düz metni güvenli hale getirir. Mailler Markdown'dan üretildiği için
 * kullanıcı metnindeki `[yazı](adres)`, `**`, `#` gibi işaretler biçim/bağlantıya dönüşebilir
 * (örn. yoruma yazılmış sahte bir bağlantı). HTML ve Markdown işaretleri kaçışlanır.
 */
class MailText
{
    public static function md(string|Htmlable|null $text): string
    {
        if ($text instanceof Htmlable) {
            return $text->toHtml();
        }

        $html = htmlspecialchars((string) $text, ENT_NOQUOTES, 'UTF-8');

        // Her Markdown işaretinin önüne ters eğik çizgi (chr(92)) koyar: "[x](y)" → "\[x\]\(y\)"
        return addcslashes($html, chr(92).'`*_{}[]()#+-.!|~>');
    }
}
