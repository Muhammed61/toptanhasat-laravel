<?php

namespace App\Filament\Resources\MarketPrices;

use App\Filament\Resources\MarketPrices\Pages\ManageMarketPrices;
use App\Models\MarketPrice;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Ana sayfadaki hal fiyatları şeridi */
class MarketPriceResource extends Resource
{
    protected static ?string $model = MarketPrice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Hal Fiyatları';

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?string $modelLabel = 'Fiyat';

    protected static ?string $pluralModelLabel = 'Hal Fiyatları';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('product')->label('Ürün')->required()->maxLength(50)->columnSpanFull(),
                TextInput::make('price')->label('Fiyat')->numeric()->minValue(0)->required()->suffix('₺'),
                TextInput::make('change')->label('Değişim')->numeric()->default(0)->suffix('%')
                    ->helperText('Artış için pozitif, düşüş için negatif (örn. -1,5).'),
                Toggle::make('is_active')->label('Şeritte göster')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Aktif fiyatlar ana sayfanın üstündeki şeritte görünür. Hiç aktif fiyat yoksa şerit gizlenir.')
            ->reorderable('sort')
            ->defaultSort('sort')
            ->paginated(false)
            ->columns([
                TextColumn::make('product')->label('Ürün')->weight('medium')->searchable(),
                TextColumn::make('price')->label('Fiyat')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.').' ₺'),
                TextColumn::make('change')->label('Değişim')
                    ->formatStateUsing(fn ($state) => '%'.number_format((float) $state, 2, ',', '.'))
                    ->badge()
                    ->color(fn ($state) => (float) $state > 0 ? 'success' : ((float) $state < 0 ? 'danger' : 'gray')),
                ToggleColumn::make('is_active')->label('Şeritte'),
                TextColumn::make('updated_at')->label('Güncellendi')->since()->dateTimeTooltip('d.m.Y H:i'),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
                DeleteAction::make()->label('Sil'),
            ])
            ->emptyStateHeading('Henüz fiyat girilmedi');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMarketPrices::route('/'),
        ];
    }
}
