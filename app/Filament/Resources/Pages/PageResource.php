<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use App\Support\IconOptions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Sitedeki sabit sayfaların yazıları ve SEO bilgileri. Sayfa eklenip silinmez, sadece düzenlenir. */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Sayfalar';

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?string $modelLabel = 'Sayfa';

    protected static ?string $pluralModelLabel = 'Sayfalar';

    protected static ?int $navigationSort = 1;

    /** Vue sitesindeki adresler */
    public const PATHS = [
        'home' => '/', 'about' => '/about', 'how-it-works' => '/how-it-works', 'contact' => '/contact',
        'auth' => '/auth', 'kvkk' => '/kvkk', 'terms' => '/terms', 'cookie' => '/cookie',
    ];

    public static function form(Schema $schema): Schema
    {
        $is = fn (string ...$slugs) => fn (?Page $record) => in_array($record?->slug, $slugs, true);
        $icon = fn () => Select::make('icon')->label('İkon')->options(IconOptions::ALL)->required()->native(false);

        return $schema
            ->columns(1)
            ->components([
                Section::make('Başlık')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label(fn (?Page $record) => $record?->slug === 'home' ? 'Büyük başlık' : 'Sayfa başlığı')
                            ->required()->maxLength(120),
                        TextInput::make('subtitle')->label('Alt başlık')->maxLength(200),
                    ]),

                // Uzun yazılı sayfalar
                Section::make('Sayfa metni')
                    ->visible($is(...Page::RICH_TEXT))
                    ->schema([
                        RichEditor::make('body')
                            ->hiddenLabel()
                            ->toolbarButtons([
                                ['bold', 'italic', 'link'],
                                ['h2', 'h3'],
                                ['bulletList', 'orderedList', 'blockquote'],
                                ['table'],
                                ['undo', 'redo'],
                            ]),
                        DatePicker::make('content.last_updated')->label('Son güncellenme tarihi')
                            ->displayFormat('d.m.Y')
                            ->visible($is('kvkk', 'terms', 'cookie')),
                    ]),

                // Ana sayfa
                Section::make('Ana sayfa')
                    ->visible($is('home'))
                    ->columns(2)
                    ->schema([
                        TagsInput::make('content.popular_searches')->label('Popüler aramalar')
                            ->helperText('Arama kutusunun altındaki hızlı seçim etiketleri. Ürün adını yazıp Enter\'a basın.')
                            ->columnSpanFull(),
                        TextInput::make('content.ticker_title')->label('Fiyat şeridi başlığı')->maxLength(30),
                        TextInput::make('content.ticker_unit')->label('Fiyat birimi')->maxLength(15),
                    ]),

                // Hakkımızda
                Section::make('Değerlerimiz')
                    ->visible($is('about'))
                    ->schema([
                        Repeater::make('content.values')->hiddenLabel()
                            ->schema([$icon(), TextInput::make('title')->label('Başlık')->required()->maxLength(40), Textarea::make('text')->label('Açıklama')->rows(2)->maxLength(160)])
                            ->columns(3)->maxItems(8)->reorderable()->collapsible()
                            ->itemLabel(fn (array $state) => $state['title'] ?? null)
                            ->addActionLabel('Değer ekle'),
                    ]),

                // Nasıl Çalışır
                Section::make('Adımlar')
                    ->visible($is('how-it-works'))
                    ->schema([
                        Repeater::make('content.steps')->hiddenLabel()
                            ->schema([$icon(), TextInput::make('title')->label('Başlık')->required()->maxLength(40), Textarea::make('text')->label('Açıklama')->rows(2)->maxLength(120)])
                            ->columns(3)->maxItems(6)->reorderable()->collapsible()
                            ->itemLabel(fn (array $state) => $state['title'] ?? null)
                            ->addActionLabel('Adım ekle'),
                        Textarea::make('content.notice')->label('Bilgi kutusu')->rows(2)->maxLength(300)
                            ->helperText('Adımların altında yeşil kutuda görünür.'),
                    ]),
                Section::make('Sıkça Sorulan Sorular')
                    ->visible($is('how-it-works'))
                    ->schema([
                        Repeater::make('content.faqs')->hiddenLabel()
                            ->schema([TextInput::make('question')->label('Soru')->required()->maxLength(120), Textarea::make('answer')->label('Cevap')->required()->rows(2)->maxLength(500)])
                            ->reorderable()->collapsible()
                            ->itemLabel(fn (array $state) => $state['question'] ?? null)
                            ->addActionLabel('Soru ekle'),
                    ]),
                Section::make('Alttaki çağrı bandı')
                    ->visible($is('how-it-works'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('content.cta_title')->label('Başlık')->maxLength(60),
                        TextInput::make('content.cta_text')->label('Yazı')->maxLength(120),
                        TextInput::make('content.cta_button')->label('Buton yazısı')->maxLength(30),
                    ]),

                // İletişim
                Section::make('İletişim formu')
                    ->visible($is('contact'))
                    ->description('Adres, telefon ve e-posta "Site Ayarları → İletişim" sekmesinden yönetilir.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('content.form_title')->label('Form başlığı')->maxLength(60),
                        TextInput::make('content.form_subtitle')->label('Form açıklaması')->maxLength(150),
                        TextInput::make('content.success_message')->label('Gönderim sonrası mesaj')->maxLength(150)->columnSpanFull(),
                    ]),

                // Giriş / Üye Ol
                Section::make('Yeşil tanıtım paneli')
                    ->visible($is('auth'))
                    ->schema([
                        TagsInput::make('content.highlights')->label('Öne çıkanlar')
                            ->helperText('Tikli maddeler. Yazıp Enter\'a basın.'),
                    ]),

                Section::make('SEO')
                    ->description('Boş bırakılırsa sayfa başlığı ve Site Ayarları\'ndaki varsayılan açıklama kullanılır.')
                    ->collapsible()
                    ->schema([
                        TextInput::make('seo_title')->label('Google başlığı')->maxLength(70),
                        Textarea::make('seo_description')->label('Google açıklaması')->rows(2)->maxLength(160),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->defaultSort('id')
            ->columns([
                TextColumn::make('slug')->label('Sayfa')
                    ->formatStateUsing(fn (Page $record) => $record->label())
                    ->description(fn (Page $record) => self::PATHS[$record->slug] ?? null)
                    ->weight('medium'),
                TextColumn::make('title')->label('Başlık')->limit(50),
                TextColumn::make('updated_at')->label('Son düzenleme')->since()->dateTimeTooltip('d.m.Y H:i'),
            ])
            ->recordActions([
                Action::make('view')->label('Sitede gör')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn (Page $record) => rtrim(config('app.frontend_url'), '/').(self::PATHS[$record->slug] ?? '/'), shouldOpenInNewTab: true),
                EditAction::make()->label('Düzenle'),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
