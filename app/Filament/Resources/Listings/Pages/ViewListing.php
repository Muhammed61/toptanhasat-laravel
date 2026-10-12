<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\Listings\ListingActions;
use App\Filament\Resources\Listings\ListingResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewListing extends ViewRecord
{
    protected static string $resource = ListingResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->title();
    }

    protected function getHeaderActions(): array
    {
        return [
            ListingActions::approve(),
            ListingActions::reject(),
            EditAction::make()->label('Düzenle')->color('gray'),
            Action::make('conversations')
                ->label(fn () => 'Mesajlaşmalar ('.$this->getRecord()->conversations()->count().')')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->visible(fn () => $this->getRecord()->conversations()->exists())
                ->url(fn () => ConversationResource::getUrl('index', ['filters' => ['listing' => ['value' => $this->getRecord()->getKey()]]])),
            // Onaysız ilan da adminlere sitede önizleme olarak açılır
            Action::make('viewOnSite')
                ->label('Sitede gör')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => rtrim(config('app.frontend_url'), '/').'/ilan/'.$this->getRecord()->getKey(), shouldOpenInNewTab: true),
        ];
    }
}
