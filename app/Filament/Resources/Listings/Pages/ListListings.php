<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Enums\ListingStatus;
use App\Filament\Resources\Listings\ListingResource;
use App\Models\Listing;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListListings extends ListRecords
{
    protected static string $resource = ListingResource::class;

    public function getTabs(): array
    {
        // Rozet sayıları closure: onay/red sonrası yeniden çizimde güncel kalsın
        $tab = fn (ListingStatus $status, string $label) => Tab::make($label)
            ->badge(fn () => Listing::where('status', $status)->count())
            ->badgeColor($status->getColor())
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));

        return [
            'pending' => $tab(ListingStatus::Pending, 'Onay bekleyen'),
            'approved' => $tab(ListingStatus::Approved, 'Yayında'),
            'rejected' => $tab(ListingStatus::Rejected, 'Reddedilen'),
            'all' => Tab::make('Tümü'),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'pending';
    }
}
