<?php

namespace App\Filament\Resources\Listings\Tables;

use App\Enums\ListingType;
use App\Filament\Resources\Listings\ListingActions;
use App\Models\Category;
use App\Models\Listing;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ListingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Listing $record) => route('filament.admin.resources.listings.view', $record))
            ->columns([
                TextColumn::make('product')
                    ->label('İlan')
                    ->formatStateUsing(fn (Listing $record) => $record->title())
                    ->description(fn (Listing $record) => $record->price_negotiable || $record->price === null
                        ? 'Fiyat görüşülür'
                        : number_format((float) $record->price, 2, ',', '.')." ₺ / {$record->unit}")
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tür')
                    ->badge(),
                TextColumn::make('city.name')
                    ->label('Konum')
                    ->formatStateUsing(fn (Listing $record) => "{$record->district->name}, {$record->city->name}")
                    ->searchable(),
                TextColumn::make('user.first_name')
                    ->label('İlan sahibi')
                    ->formatStateUsing(fn (Listing $record) => $record->user->fullName())
                    ->description(fn (Listing $record) => $record->user->phone)
                    ->searchable(['first_name', 'last_name', 'phone']),
                TextColumn::make('status')
                    ->label('Durum')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->since()
                    ->dateTimeTooltip('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tür')->options(ListingType::class),
                SelectFilter::make('city')->label('İl')->relationship('city', 'name')->searchable()->preload(),
                SelectFilter::make('category')->label('Kategori')->options(
                    fn () => Category::orderBy('sort')->pluck('name', 'slug')
                ),
            ])
            ->recordActions([
                ListingActions::approve()->iconButton()->tooltip('Onayla'),
                ListingActions::reject()->iconButton()->tooltip('Reddet'),
                ActionGroup::make([
                    ViewAction::make()->label('Görüntüle'),
                    EditAction::make()->label('Düzenle'),
                    DeleteAction::make()->label('Sil'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ListingActions::bulkApprove(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Bu listede ilan yok')
            ->emptyStateIcon('heroicon-o-rectangle-stack');
    }
}
