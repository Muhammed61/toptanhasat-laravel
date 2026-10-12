<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\Listing;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Kategoriler';

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?string $modelLabel = 'Kategori';

    protected static ?string $pluralModelLabel = 'Kategoriler';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Kategori')
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')->label('Ad')->required()->maxLength(50)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, ?Category $record) {
                                if (! $record) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')->label('Adres anahtarı')->required()->maxLength(30)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?Category $record) => $record !== null)
                            ->dehydrated(fn (?Category $record) => $record === null)
                            ->helperText('İlanlarda saklanır; oluşturulduktan sonra değiştirilemez.'),
                        Select::make('icon')->label('İkon')->options(Category::ICONS)->required()->native(false),
                        TextInput::make('badge')->label('Etiket')->maxLength(20)->placeholder('Örn. Sezonda')
                            ->helperText('Ana sayfadaki kartın köşesinde turuncu etiket.'),
                        Toggle::make('is_active')->label('Sitede göster')->default(true)->inline(false),
                    ]),
                Section::make('Ürünler')
                    ->description('Sürükleyerek sıralayabilirsiniz. Pasif ürünler yeni ilanlarda seçilemez.')
                    ->schema([
                        Repeater::make('products')->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort')
                            ->schema([
                                TextInput::make('name')->label('Ürün adı')->required()->maxLength(50)->distinct(),
                                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
                            ])
                            ->columns(2)
                            ->grid(2)
                            ->addActionLabel('Ürün ekle')
                            ->defaultItems(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->paginated(false)
            ->modifyQueryUsing(fn ($query) => $query->withCount('products'))
            ->columns([
                TextColumn::make('name')->label('Kategori')->weight('medium')->searchable(),
                TextColumn::make('icon')->label('İkon')->formatStateUsing(fn (string $state) => Category::ICONS[$state] ?? $state),
                TextColumn::make('badge')->label('Etiket')->badge()->color('warning')->placeholder('-'),
                TextColumn::make('products_count')->label('Ürün')->alignCenter(),
                ToggleColumn::make('is_active')->label('Sitede'),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
                DeleteAction::make()->label('Sil')
                    ->hidden(fn (Category $record) => Listing::where('category', $record->slug)->exists())
                    ->modalDescription('Kategori ve ürünleri silinir.'),
            ])
            ->emptyStateHeading('Henüz kategori yok');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
