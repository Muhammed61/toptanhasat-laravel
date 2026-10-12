<?php

namespace App\Filament\Resources\SiteSettings\Pages;

use App\Filament\Resources\SiteSettings\SiteSettingResource;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditSiteSettings extends EditRecord
{
    protected static string $resource = SiteSettingResource::class;

    /** Rota parametresi yok: her zaman tek ayar kaydı düzenlenir */
    public function mount(int|string|null $record = null): void
    {
        parent::mount(SiteSetting::current()->getKey());
    }

    public function getTitle(): string
    {
        return 'Site Ayarları';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewSite')
                ->label('Siteyi görüntüle')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(config('app.frontend_url'), shouldOpenInNewTab: true),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Ayarlar kaydedildi';
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
