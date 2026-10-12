<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Profil fotoğrafı olmayan kullanıcılar için baş harf avatarı.
 * Filament'in varsayılanı ui-avatars.com'a istek atıp kullanıcı adını dışarı gönderir;
 * bu sınıf SVG'yi sunucuda üretir, dışarıya hiçbir şey gitmez.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = Filament::getNameForDefaultAvatar($record);

        $initials = collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1), 'UTF-8'))
            ->join('');

        $initials = e(Str::of($initials)->whenEmpty(fn () => Str::of('?')));

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
                <rect width="64" height="64" fill="#2D6A4F"/>
                <text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#ffffff"
                      font-family="Inter, Arial, sans-serif" font-size="26" font-weight="600">{$initials}</text>
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
