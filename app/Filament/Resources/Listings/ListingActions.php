<?php

namespace App\Filament\Resources\Listings;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Filament\Resources\Users\UserResource;
use App\Models\Listing;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** Tablo ve detay sayfasında ortak kullanılan onay / red aksiyonları */
class ListingActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Onayla')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->visible(fn (Listing $record) => $record->status !== ListingStatus::Approved)
            ->requiresConfirmation()
            ->modalHeading(fn (Listing $record) => "\"{$record->title()}\" yayına alınsın mı?")
            ->modalSubmitActionLabel('Onayla ve yayınla')
            ->action(function (Listing $record) {
                if (self::needsCks($record)) {
                    Notification::make()
                        ->title('Önce üyenin ÇKS belgesini onaylayın')
                        ->body('Satılık ilan, sahibinin ÇKS belgesi onaylanmadan yayına alınamaz.')
                        ->danger()
                        ->actions([Action::make('user')->label('Üyeye git')->url(UserResource::getUrl('edit', ['record' => $record->user]))])
                        ->persistent()
                        ->send();

                    return;
                }

                $record->approve(auth()->user());
                Notification::make()->title('İlan yayına alındı')->success()->send();
            });
    }

    /** Satılık ilanın sahibinin ÇKS'si onaylı değilse yayına alınamaz */
    public static function needsCks(Listing $listing): bool
    {
        return $listing->type === ListingType::Sell && $listing->user && ! $listing->user->canPublishSellListing();
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Reddet')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (Listing $record) => $record->status !== ListingStatus::Rejected)
            ->modalHeading(fn (Listing $record) => "\"{$record->title()}\" reddedilsin mi?")
            ->modalDescription('Red sebebi ilan sahibine gösterilir.')
            ->schema([
                Textarea::make('reason')
                    ->label('Red sebebi')
                    ->placeholder('Örn. Fotoğraflar ürünle ilgili değil.')
                    ->required()
                    ->maxLength(255),
            ])
            ->modalSubmitActionLabel('Reddet')
            ->action(function (Listing $record, array $data) {
                $record->reject(auth()->user(), $data['reason']);
                Notification::make()->title('İlan reddedildi')->warning()->send();
            });
    }

    public static function bulkApprove(): BulkAction
    {
        return BulkAction::make('bulkApprove')
            ->label('Seçilenleri onayla')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->requiresConfirmation()
            ->action(function (Collection $records) {
                [$blocked, $ok] = $records->partition(fn (Listing $listing) => self::needsCks($listing));
                $ok->each(fn (Listing $listing) => $listing->approve(auth()->user()));

                Notification::make()->title("{$ok->count()} ilan yayına alındı")->success()->send();
                if ($blocked->isNotEmpty()) {
                    Notification::make()
                        ->title("{$blocked->count()} satılık ilan yayına alınmadı")
                        ->body('Sahiplerinin ÇKS belgesi henüz onaylanmamış.')
                        ->warning()
                        ->send();
                }
            })
            ->deselectRecordsAfterCompletion();
    }
}
