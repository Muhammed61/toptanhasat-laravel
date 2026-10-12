<?php

namespace App\Models\Concerns;

use App\Support\SiteData;

/** Kaydedilince/silinince /api/site önbelleğini temizler; panel değişikliği sitede hemen görünür. */
trait FlushesSiteCache
{
    protected static function bootFlushesSiteCache(): void
    {
        static::saved(fn () => SiteData::flush());
        static::deleted(fn () => SiteData::flush());
    }
}
