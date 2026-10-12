<?php

namespace App\Filament\Resources\SiteSettings\Pages;

use App\Filament\Resources\SiteSettings\SiteSettingResource;
use App\Models\SiteSetting;
use App\Support\MailSettings;
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

    /** Hiç kaydedilmemiş anahtarlar için varsayılanlar (anahtar yoksa form kapalı gösterirdi) */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['data']['messaging_enabled'] ??= true;

        $data['data']['mail'] = [
            'driver' => 'log',
            'encryption' => 'tls',
            'port' => 587,
            ...($data['data']['mail'] ?? []),
            'password' => null, // kayıtlı şifre tarayıcıya gönderilmez
        ];

        return $data;
    }

    /** SMTP şifresi: boş bırakıldıysa kayıtlı (şifreli) değer korunur, yazıldıysa şifrelenip saklanır */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $password = $data['data']['mail']['password'] ?? null;

        $data['data']['mail']['password'] = filled($password)
            ? MailSettings::encrypt($password)
            : data_get($this->getRecord()->data, 'mail.password');

        return $data;
    }

    protected function afterSave(): void
    {
        MailSettings::apply();
        $this->data['data']['mail']['password'] = null; // kaydedilen şifre ekranda kalmasın
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
