<?php

namespace App\Filament\Resources\SiteSettings;

use App\Filament\Resources\SiteSettings\Pages\EditSiteSettings;
use App\Models\SiteSetting;
use App\Support\AdminMail;
use App\Support\MailSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;
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

                    Tab::make('Mesajlaşma ve Yorumlar')->icon(Heroicon::OutlinedChatBubbleLeftRight)->schema([
                        Toggle::make('data.messaging_enabled')->label('Site içi mesajlaşma açık')
                            ->helperText('Kapalıyken üyeler birbirine mesaj gönderemez; "Mesaj gönder" düğmesi ve Mesajlar sayfası sitede gizlenir. '
                                .'Telefonunu paylaşan satıcılar telefonla aranmaya devam eder. Eski mesajlar silinmez; panelden görebilir, '
                                .'tekrar açtığınızda üyeler kaldıkları yerden devam eder.'),
                        Toggle::make('data.comments_enabled')->label('İlan yorumları açık')
                            ->helperText('Kapalıyken ilan sayfalarındaki Yorumlar bölümü gizlenir ve yeni yorum yazılamaz. '
                                .'Mevcut yorumlar silinmez; tekrar açtığınızda yeniden görünür.'),
                    ]),

                    Tab::make('SMTP Ayarları')->icon(Heroicon::OutlinedEnvelope)->schema([
                        Section::make('E-posta Ayarları')
                            ->afterHeader([
                                Text::make(fn () => MailSettings::statusLabel())
                                    ->badge()
                                    ->color(fn () => MailSettings::isActive() ? 'success' : 'gray'),
                            ])
                            ->schema([
                                Callout::make()
                                    ->info()
                                    ->description('Sitedeki tüm mail gönderimleri (iletişim formu ve ileride eklenecek bildirim mailleri) buradaki ayarlarla yapılır. Sunucudaki .env dosyasının mail ayarları kullanılmaz.'),

                                Grid::make(3)->schema([
                                    TextInput::make('data.mail.recipient')->label('Siteden Gelen Mailler (Alıcı)')
                                        ->email()->maxLength(100)
                                        ->helperText('İletişim formu ve yönetici bildirim mailleri bu adrese gelir.'),
                                    TextInput::make('data.mail.from_address')->label('Gönderici E-posta')
                                        ->email()->maxLength(100)
                                        ->helperText('Maillerin "kimden" adresi. Genelde SMTP kullanıcısıyla aynı olmalı.'),
                                    TextInput::make('data.mail.from_name')->label('Gönderici İsim')
                                        ->maxLength(100)
                                        ->helperText('Gelen kutusunda gönderen adı olarak görünür.'),
                                ]),

                                Grid::make(5)->schema([
                                    Select::make('data.mail.driver')->label('Mail Sürücüsü')
                                        ->options(MailSettings::DRIVERS)->default('log')->selectablePlaceholder(false),
                                    TextInput::make('data.mail.host')->label('SMTP Host')->placeholder('smtp.gmail.com')->maxLength(150)
                                        ->columnSpan(2),
                                    TextInput::make('data.mail.port')->label('SMTP Port')->numeric()->minValue(1)->maxValue(65535)->placeholder('587'),
                                    Select::make('data.mail.encryption')->label('Şifreleme')
                                        ->options(MailSettings::ENCRYPTIONS)->default('tls')->selectablePlaceholder(false),
                                    TextInput::make('data.mail.username')->label('SMTP Kullanıcı')->maxLength(150)
                                        ->columnSpan(2),
                                    TextInput::make('data.mail.password')->label('SMTP Şifre')
                                        ->password()->revealable()->maxLength(200)->autocomplete('new-password')
                                        ->helperText('Şifre veritabanında şifrelenmiş saklanır. Göz ikonuyla gösterip gizleyebilirsiniz.')
                                        ->columnSpan(3),
                                ]),

                                CheckboxList::make('data.mail.admin_alerts')
                                    ->label('Yöneticiye bildirim mailleri')
                                    ->helperText('Seçili olaylarda "Siteden Gelen Mailler (Alıcı)" adresine mail gider. Üye mesajlarında yalnızca yeni başlayan konuşma bildirilir.')
                                    ->options(AdminMail::EVENTS)
                                    ->columns(3)
                                    ->bulkToggleable(),

                                Actions::make([
                                    Action::make('sendTestMail')
                                        ->label('Test Maili Gönder')
                                        ->icon(Heroicon::OutlinedPaperAirplane)
                                        ->outlined()
                                        ->action(fn () => self::sendTestMail()),
                                ]),
                                Text::make('Önce ayarları kaydedin; test maili kayıtlı ayarlarla "Siteden Gelen Mailler (Alıcı)" adresine gönderilir.')
                                    ->color('gray'),
                            ]),
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

    /** Kayıtlı SMTP ayarlarıyla alıcı adresine deneme maili; hata olursa sebebini gösterir */
    public static function sendTestMail(): void
    {
        MailSettings::apply();
        $s = MailSettings::get();

        if (blank($s['recipient'])) {
            Notification::make()->title('Önce "Siteden Gelen Mailler (Alıcı)" adresini girip kaydedin')->warning()->send();

            return;
        }

        if (! MailSettings::isActive($s)) {
            Notification::make()->title('Mail gönderimi pasif')
                ->body('Mail sürücüsünü SMTP seçin, SMTP host ve gönderici e-postayı girip kaydedin.')
                ->warning()->send();

            return;
        }

        try {
            Mail::raw(
                'Bu bir test mailidir. Bu maili aldıysanız '.SiteSetting::get('site_name', 'Toptanhasat').' SMTP ayarları doğru çalışıyor.',
                fn ($message) => $message->to($s['recipient'])->subject('Test maili - '.SiteSetting::get('site_name', 'Toptanhasat')),
            );
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Test maili gönderilemedi')
                ->body(Str::limit($e->getMessage(), 300))
                ->danger()->persistent()->send();

            return;
        }

        Notification::make()->title("Test maili {$s['recipient']} adresine gönderildi")->success()->send();
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
