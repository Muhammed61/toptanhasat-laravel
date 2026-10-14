<?php

namespace App\Filament\Resources\ListingComments\Pages;

use App\Filament\Resources\ListingComments\ListingCommentResource;
use App\Models\SiteSetting;
use App\Support\AdminNotifier;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListListingComments extends ListRecords
{
    protected static string $resource = ListingCommentResource::class;

    /** Liste açılınca yeni yorum bildirimleri okundu sayılır (menüdeki sayı sıfırlanır) */
    public function mount(): void
    {
        parent::mount();
        AdminNotifier::markSeen(AdminNotifier::TYPE_COMMENT);
    }

    public function getSubheading(): ?string
    {
        return SiteSetting::commentsEnabled()
            ? 'İlan yorumları açık: yorumlar onay beklemeden yayınlanır; uygunsuz olanları düzenleyebilir ya da silebilirsiniz.'
            : 'İlan yorumları kapalı: ilan sayfalarında Yorumlar bölümü gizli. Mevcut yorumlar aşağıda duruyor.';
    }

    /** Site Ayarları → Mesajlaşma ve Yorumlar ile aynı ayar; buradan hızlıca açılıp kapatılır */
    protected function getHeaderActions(): array
    {
        $on = fn (): bool => SiteSetting::commentsEnabled();

        return [
            Action::make('toggleComments')
                ->label(fn () => $on() ? 'Yorumları kapat' : 'Yorumları aç')
                ->icon(fn () => $on() ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                ->color(fn () => $on() ? 'danger' : 'success')
                ->requiresConfirmation()
                ->modalHeading(fn () => $on() ? 'İlan yorumları kapatılsın mı?' : 'İlan yorumları açılsın mı?')
                ->modalDescription(fn () => $on()
                    ? 'İlan sayfalarındaki Yorumlar bölümü gizlenir ve yeni yorum yazılamaz. Mevcut yorumlar silinmez.'
                    : 'İlan sayfalarında Yorumlar bölümü tekrar görünür ve herkes yorum yazabilir.')
                ->modalSubmitActionLabel(fn () => $on() ? 'Kapat' : 'Aç')
                ->action(function () use ($on) {
                    $enable = ! $on();
                    SiteSetting::put('comments_enabled', $enable);

                    Notification::make()->title($enable ? 'İlan yorumları açıldı' : 'İlan yorumları kapatıldı')->success()->send();
                }),
        ];
    }
}
