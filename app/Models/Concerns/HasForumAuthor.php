<?php

namespace App\Models\Concerns;

use App\Models\SiteSetting;

/**
 * Forum konusu ve cevabının yazarı: üye, misafir ("Misafir04217") ya da site ekibi.
 * Yöneticinin yazdıkları "Toptanhasat Ekibi" adıyla ve "Ekip" rozetiyle görünür.
 */
trait HasForumAuthor
{
    public function isTeam(): bool
    {
        return (bool) $this->user?->is_admin;
    }

    public function authorName(): string
    {
        if ($this->isTeam()) {
            return SiteSetting::get('site_name', 'Toptanhasat').' Ekibi';
        }

        return $this->user?->fullName() ?? $this->author_name ?? 'Misafir';
    }
}
