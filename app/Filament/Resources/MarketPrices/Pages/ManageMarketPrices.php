<?php

namespace App\Filament\Resources\MarketPrices\Pages;

use App\Filament\Resources\MarketPrices\MarketPriceResource;
use App\Support\SiteData;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMarketPrices extends ManageRecords
{
    protected static string $resource = MarketPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Fiyat ekle')];
    }

    /** Sürükle-bırak sıralama toplu update yapar (model olayı yok); site önbelleğini elle temizle */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        parent::reorderTable($order, $draggedRecordKey);
        SiteData::flush();
    }
}
