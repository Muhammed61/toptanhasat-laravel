<?php

namespace App\Filament\Resources\Conversations\Pages;

use App\Filament\Resources\Conversations\ConversationResource;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListConversations extends ListRecords
{
    protected static string $resource = ConversationResource::class;

    public function getSubheading(): ?string
    {
        return SiteSetting::messagingEnabled()
            ? 'Site içi mesajlaşma açık: üyeler ilan sayfasından birbirine mesaj gönderebilir.'
            : 'Site içi mesajlaşma kapalı: sitede "Mesaj gönder" ve Mesajlar sayfası gizli. Eski mesajlar aşağıda duruyor.';
    }

    /** Site Ayarları → Mesajlaşma ile aynı ayar; buradan hızlıca açılıp kapatılır */
    protected function getHeaderActions(): array
    {
        // Değerler her çizimde yeniden okunur; açıp kapatınca düğme hemen güncellenir
        $on = fn (): bool => SiteSetting::messagingEnabled();

        return [
            Action::make('toggleMessaging')
                ->label(fn () => $on() ? 'Mesajlaşmayı kapat' : 'Mesajlaşmayı aç')
                ->icon(fn () => $on() ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                ->color(fn () => $on() ? 'danger' : 'success')
                ->requiresConfirmation()
                ->modalHeading(fn () => $on() ? 'Site içi mesajlaşma kapatılsın mı?' : 'Site içi mesajlaşma açılsın mı?')
                ->modalDescription(fn () => $on()
                    ? 'Üyeler birbirine mesaj gönderemez, sitede mesaj düğmeleri gizlenir. Eski mesajlar silinmez; tekrar açtığınızda kaldıkları yerden devam ederler.'
                    : 'Üyeler ilan sayfasından birbirine tekrar mesaj gönderebilir.')
                ->modalSubmitActionLabel(fn () => $on() ? 'Kapat' : 'Aç')
                ->action(function () use ($on) {
                    $enable = ! $on();
                    $setting = SiteSetting::current();
                    $setting->data = [...$setting->data, 'messaging_enabled' => $enable];
                    $setting->save();

                    Notification::make()
                        ->title($enable ? 'Mesajlaşma açıldı' : 'Mesajlaşma kapatıldı')
                        ->success()
                        ->send();
                }),
        ];
    }
}
