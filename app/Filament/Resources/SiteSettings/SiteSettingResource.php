<?php

namespace App\Filament\Resources\SiteSettings;

use App\Filament\Resources\SiteSettings\Pages\EditSiteSettings;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Tek kayıtlık site ayarları; menüden doğrudan düzenleme ekranı açılır. */
class SiteSettingResource extends Resource
{
    protected static ?string $model = SiteSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Site Ayarları';

    protected static string|UnitEnum|null $navigationGroup = 'Ayarlar';

    protected static ?string $modelLabel = 'Site Ayarları';

    protected static ?string $pluralModelLabel = 'Site Ayarları';

    public static function form(Schema $schema): Schema
    {
        $image = fn (string $name, string $label) => FileUpload::make($name)
            ->label($label)
            ->image()
            ->disk('public')
            ->directory('site')
            ->visibility('public')
            ->maxSize(2048);

        return $schema
            ->columns(1)
            ->components([
                Tabs::make('settings')->persistTabInQueryString()->tabs([
                    Tab::make('Genel')->icon(Heroicon::OutlinedHome)->schema([
                        TextInput::make('data.site_name')->label('Site adı')->required()->maxLength(60)
                            ->helperText('Logo yüklenmezse üst menüde bu ad yazı olarak görünür.'),
                        Textarea::make('data.footer_text')->label('Alt bilgi tanıtım yazısı')->rows(2)->maxLength(200),
                        Textarea::make('data.footer_disclaimer')->label('Alt bilgi uyarı notu')->rows(2)->maxLength(250)
                            ->helperText('Örn. aracı platform olduğunuzu belirten not.'),
                        TextInput::make('data.copyright')->label('Telif yazısı')->prefix('© '.date('Y'))->maxLength(100),
                    ]),

                    Tab::make('Logo')->icon(Heroicon::OutlinedPhoto)->schema([
                        Section::make()->columns(3)->schema([
                            $image('data.logo', 'Logo')
                                ->helperText('Açık zeminde kullanılır (üst menü). Yatay, şeffaf arka planlı PNG/SVG önerilir.'),
                            $image('data.logo_light', 'Koyu zemin logosu')
                                ->helperText('Alt bilgi ve giriş sayfasındaki koyu alanlar için beyaz logo. Boşsa varsayılan işaret kullanılır.'),
                            $image('data.favicon', 'Favicon')
                                ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml'])
                                ->helperText('Tarayıcı sekmesindeki küçük ikon. Kare, en az 64×64 PNG.'),
                        ]),
                    ]),

                    Tab::make('İletişim')->icon(Heroicon::OutlinedPhone)->columns(2)->schema([
                        // tel() parantezli biçimi ("+90 (555) ...") reddettiği için serbest metin
                        TextInput::make('data.phone')->label('Telefon')->maxLength(30)->placeholder('+90 (555) 123 45 67'),
                        TextInput::make('data.email')->label('E-posta')->email()->maxLength(100),
                        TextInput::make('data.address')->label('Adres')->maxLength(200),
                        TextInput::make('data.working_hours')->label('Çalışma saatleri')->maxLength(100)
                            ->placeholder('Pazartesi - Cuma, 09:00 - 18:00'),
                    ]),

                    Tab::make('Mesajlaşma')->icon(Heroicon::OutlinedChatBubbleLeftRight)->schema([
                        Toggle::make('data.messaging_enabled')->label('Site içi mesajlaşma açık')
                            ->helperText('Kapalıyken üyeler birbirine mesaj gönderemez; "Mesaj gönder" düğmesi ve Mesajlar sayfası sitede gizlenir. '
                                .'Telefonunu paylaşan satıcılar telefonla aranmaya devam eder. Eski mesajlar silinmez; panelden görebilir, '
                                .'tekrar açtığınızda üyeler kaldıkları yerden devam eder.'),
                    ]),

                    Tab::make('Sosyal Medya')->icon(Heroicon::OutlinedShare)->schema([
                        Section::make()
                            ->description('Boş bırakılan hesapların ikonu sitede gösterilmez.')
                            ->columns(2)
                            ->schema(collect([
                                'facebook' => 'Facebook', 'x' => 'X (Twitter)', 'instagram' => 'Instagram',
                                'linkedin' => 'LinkedIn', 'youtube' => 'YouTube',
                            ])->map(fn ($label, $key) => TextInput::make("data.social.$key")->label($label)->url()->placeholder('https://'))
                                ->values()->all()),
                    ]),

                    Tab::make('SEO')->icon(Heroicon::OutlinedMagnifyingGlass)->schema([
                        TextInput::make('data.seo.default_title')->label('Varsayılan sayfa başlığı')->maxLength(70)
                            ->helperText('Ana sayfada ve kendi başlığı olmayan sayfalarda tarayıcı sekmesinde ve Google sonuçlarında görünür.'),
                        TextInput::make('data.seo.title_suffix')->label('Başlık eki')->maxLength(30)
                            ->helperText('Diğer sayfaların başlığının sonuna eklenir. Örn. " | Toptanhasat"'),
                        Textarea::make('data.seo.description')->label('Varsayılan açıklama')->rows(3)->maxLength(160)
                            ->helperText('Google sonuçlarında başlığın altında görünen kısa açıklama (en fazla 160 karakter).'),
                        $image('data.seo.og_image', 'Paylaşım görseli')
                            ->helperText('Site WhatsApp, Facebook vb. yerlerde paylaşıldığında görünen görsel. 1200×630 önerilir.'),
                        TextInput::make('data.seo.google_site_verification')->label('Google Search Console doğrulama kodu')
                            ->helperText('Search Console\'un verdiği <meta name="google-site-verification" content="..."> etiketindeki content değeri.')
                            ->maxLength(100),
                    ]),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => EditSiteSettings::route('/'),
        ];
    }
}
