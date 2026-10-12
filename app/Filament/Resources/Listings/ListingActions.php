<?php

namespace App\Filament\Resources\Listings;

use App\Enums\ListingStatus;
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
                $record->approve(auth()->user());
                Notification::make()->title('İlan yayına alındı')->success()->send();
            });
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
                $records->each(fn (Listing $listing) => $listing->approve(auth()->user()));
                Notification::make()->title("{$records->count()} ilan yayına alındı")->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
