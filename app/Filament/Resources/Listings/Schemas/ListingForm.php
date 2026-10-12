<?php

namespace App\Filament\Resources\Listings\Schemas;

use App\Enums\ListingType;
use App\Models\Category;
use App\Models\District;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/** Admin düzeltmeleri için. Durum (onay/red) bu formdan değil, aksiyonlardan değişir. */
class ListingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Ürün')
                    ->columns(3)
                    ->schema([
                        Select::make('type')->label('Tür')->options(ListingType::class)->required(),
                        Select::make('category')->label('Kategori')
                            ->options(fn () => Category::orderBy('sort')->pluck('name', 'slug'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('product', null)),
                        Select::make('product')->label('Ürün')
                            ->options(fn (Get $get) => Category::where('slug', $get('category'))->first()?->products->pluck('name', 'name') ?? [])
                            ->required(),
                        TextInput::make('amount')->label('Miktar')->numeric()->minValue(0.01)->required(),
                        Select::make('unit')->label('Birim')->options(config('catalog.units'))->required(),
                        DatePicker::make('available_on')->label('Hazır / ihtiyaç tarihi')->displayFormat('d.m.Y'),
                        Toggle::make('price_negotiable')->label('Fiyat görüşülür')->live()->inline(false),
                        TextInput::make('price')->label('Fiyat')->numeric()->suffix('₺')
                            ->disabled(fn (Get $get) => $get('price_negotiable'))
                            ->dehydrateStateUsing(fn (Get $get, $state) => $get('price_negotiable') ? null : $state)
                            ->required(fn (Get $get) => ! $get('price_negotiable')),
                        Textarea::make('description')->label('Açıklama')->maxLength(1000)->rows(4)->columnSpanFull(),
                    ]),

                Section::make('Konum')
                    ->columns(3)
                    ->schema([
                        Select::make('city_id')->label('İl')
                            ->relationship('city', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('district_id', null)),
                        Select::make('district_id')->label('İlçe')
                            ->options(fn (Get $get) => District::where('city_id', $get('city_id'))->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Toggle::make('show_phone')->label('Telefon ilanda görünsün')->inline(false),
                    ]),
            ]);
    }
}
