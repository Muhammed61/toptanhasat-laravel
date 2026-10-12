<?php

namespace App\Filament\Resources\Listings\Schemas;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ListingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    Section::make('İlan bilgileri')
                        ->columns(3)
                        ->schema([
                            TextEntry::make('type')->label('Tür')->badge(),
                            TextEntry::make('category')->label('Kategori')
                                ->formatStateUsing(fn (string $state) => Category::where('slug', $state)->value('name') ?? $state),
                            TextEntry::make('product')->label('Ürün'),
                            TextEntry::make('amount')->label('Miktar')
                                ->formatStateUsing(fn (Listing $record) => rtrim(rtrim(number_format((float) $record->amount, 2, ',', '.'), '0'), ',')." {$record->unit}"),
                            TextEntry::make('price')->label('Fiyat')
                                ->placeholder('Görüşülür')
                                ->formatStateUsing(fn (Listing $record) => number_format((float) $record->price, 2, ',', '.')." ₺ / {$record->unit}"),
                            TextEntry::make('available_on')->label('Hazır / ihtiyaç tarihi')->date('d.m.Y')->placeholder('-'),
                            TextEntry::make('description')->label('Açıklama')->placeholder('Açıklama yok')->columnSpanFull(),
                        ]),

                    Section::make('Konum')
                        ->columns(2)
                        ->schema([
                            TextEntry::make('city.name')->label('İl'),
                            TextEntry::make('district.name')->label('İlçe'),
                        ]),
                ])->columnSpan(2),

                Group::make([
                    Section::make('Durum')
                        ->schema([
                            TextEntry::make('status')->label('Durum')->badge(),
                            TextEntry::make('rejection_reason')->label('Red sebebi')
                                ->color('danger')
                                ->visible(fn (Listing $record) => $record->status === ListingStatus::Rejected),
                            TextEntry::make('created_at')->label('Gönderildi')->dateTime('d.m.Y H:i'),
                            TextEntry::make('reviewed_at')->label('İncelendi')->dateTime('d.m.Y H:i')
                                ->placeholder('Henüz incelenmedi'),
                            TextEntry::make('reviewer.first_name')->label('İnceleyen')
                                ->formatStateUsing(fn (Listing $record) => $record->reviewer?->fullName())
                                ->visible(fn (Listing $record) => $record->reviewer !== null),
                        ]),

                    Section::make('İlan sahibi')
                        ->schema([
                            TextEntry::make('user.first_name')->label('Ad soyad')
                                ->formatStateUsing(fn (Listing $record) => $record->user->fullName()),
                            TextEntry::make('user.email')->label('E-posta')->copyable(),
                            TextEntry::make('user.phone')->label('Telefon')->copyable(),
                            IconEntry::make('show_phone')->label('Telefon ilanda görünsün')->boolean(),
                        ]),
                ])->columnSpan(1),
            ]);
    }
}
