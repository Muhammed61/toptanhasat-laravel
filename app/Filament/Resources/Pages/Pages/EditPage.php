<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->label();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('Sitede gör')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(rtrim(config('app.frontend_url'), '/').(PageResource::PATHS[$this->getRecord()->slug] ?? '/'), shouldOpenInNewTab: true),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Sayfa kaydedildi';
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
