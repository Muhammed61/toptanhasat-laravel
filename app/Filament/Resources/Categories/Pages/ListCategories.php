<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Support\SiteData;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    public function getSubheading(): ?string
    {
        return 'Sıralamayı değiştirmek için sağ üstteki sıralama düğmesini kullanın. İlanı olan kategoriler silinemez; pasif yapabilirsiniz.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Yeni kategori')];
    }

    /** Sürükle-bırak sıralama toplu update yapar (model olayı yok); site önbelleğini elle temizle */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        parent::reorderTable($order, $draggedRecordKey);
        SiteData::flush();
    }
}
