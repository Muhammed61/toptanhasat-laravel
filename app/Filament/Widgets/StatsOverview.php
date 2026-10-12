<?php

namespace App\Filament\Widgets;

use App\Enums\ListingStatus;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Listing;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        $pending = Listing::where('status', ListingStatus::Pending)->count();
        $weekAgo = now()->subWeek();

        return [
            Stat::make('Onay bekleyen ilan', $pending)
                ->description($pending > 0 ? 'İncelemek için tıklayın' : 'Bekleyen ilan yok')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($pending > 0 ? 'warning' : 'gray')
                ->url(ListingResource::getUrl('index', ['activeTab' => 'pending'])),
            Stat::make('Yayındaki ilan', Listing::where('status', ListingStatus::Approved)->count())
                ->description(Listing::where('created_at', '>=', $weekAgo)->count().' yeni ilan (7 gün)')
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->color('success')
                ->url(ListingResource::getUrl('index', ['activeTab' => 'approved'])),
            Stat::make('Üye', User::count())
                ->description(User::where('created_at', '>=', $weekAgo)->count().' yeni üye (7 gün)')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->url(UserResource::getUrl('index')),
        ];
    }
}
