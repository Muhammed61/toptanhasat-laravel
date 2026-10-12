<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Support\SiteData;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Kategori kaydedildi';
    }

    /** Sadece ürünler değiştiyse kategori kaydı "saved" tetiklemeyebilir; önbelleği yine de temizle */
    protected function afterSave(): void
    {
        SiteData::flush();
    }
}
