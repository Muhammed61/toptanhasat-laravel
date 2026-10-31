<?php

namespace App\Filament\Resources\Banners;

use App\Filament\Resources\Banners\Pages\ManageBanners;
use App\Models\Banner;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Ana sayfadaki reklam görselleri (ilanların altında, yan yana kartlar) */
class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Reklam Görselleri';

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?string $modelLabel = 'Reklam görseli';

    protected static ?string $pluralModelLabel = 'Reklam Görselleri';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                FileUpload::make('image_path')->label('Görsel')
                    ->image()
                    ->disk('public')
                    ->directory('banners')
                    ->visibility('public')
                    ->maxSize(3072)
                    ->required()
                    ->helperText('Yatay görsel: 1152×660 piksel önerilir (en fazla 3 MB). Kartta bu oranda gösterilir.'),
                TextInput::make('title')->label('Başlık')->required()->maxLength(120)
                    ->helperText('Görselin altında yazar. Örn. "Narenciye sezonu başladı".'),
                TextInput::make('url')->label('Bağlantı')->maxLength(255)
                    ->placeholder('/ilan-ver')
                    ->helperText('Tıklanınca gidilecek adres. Site içi için "/forum" gibi; başka site için https:// ile başlayan tam adres. Boş bırakılırsa tıklanmaz.')
                    ->rule('regex:#^(/|https?://)#')
                    ->validationMessages(['regex' => '"/" ya da "https://" ile başlamalı.']),
                Toggle::make('is_active')->label('Sitede göster')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Aktif görseller ana sayfada ilanların altında yan yana görünür (satırda 3 tane). Sürükleyerek sıralayabilirsiniz.')
            ->reorderable('sort')
            ->defaultSort('sort')
            ->paginated(false)
            ->columns([
                ImageColumn::make('image_path')->label('Görsel')->disk('public')->imageHeight(56),
                TextColumn::make('title')->label('Başlık')->weight('medium')->searchable()
                    ->description(fn (Banner $record) => $record->url),
                ToggleColumn::make('is_active')->label('Sitede'),
                TextColumn::make('updated_at')->label('Güncellendi')->since()->dateTimeTooltip('d.m.Y H:i'),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
                DeleteAction::make()->label('Sil'),
            ])
            ->emptyStateHeading('Henüz reklam görseli yok')
            ->emptyStateDescription('Eklediğiniz görseller ana sayfada ilanların altında görünür.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBanners::route('/'),
        ];
    }
}
