<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Storage;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Sağ üst avatar → "Profil ayarları" (panel düzeninde, menüyle birlikte)
            ->profile(EditProfile::class, isSimple: false)
            ->userMenuItems([
                'profile' => fn (Action $action) => $action->label('Profil ayarları')->icon(Heroicon::OutlinedUserCircle),
            ])
            // Tema seçici menüden çıkarılıp üst bara, profil resminin soluna alındı
            ->themeSwitcher(false)
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.topbar-theme-switcher'))
            // Logonun yanında menüyü daraltma düğmesi (masaüstünde sadece ikonlar kalır)
            ->sidebarCollapsibleOnDesktop()
            // Menü logo genişliğinde (varsayılan 20rem fazla geniş); hamburger menü çizgisinin dışında durur
            ->sidebarWidth('16rem')
            // Menü grupları arasında çizgi vb. küçük stil ayarları
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.admin-styles'))
            // Menü daraltılınca logonun yerine geçen yaprak işareti
            ->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn () => view('filament.topbar-logo-mark'))
            // Baş harf avatarı sunucuda üretilir (varsayılan ui-avatars.com'a ad gönderiyordu)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // Site Ayarları'ndaki ad / logo / favicon panelde de kullanılır
            ->brandName(fn () => SiteSetting::get('site_name', 'Toptanhasat').' Yönetim')
            ->brandLogo(fn () => ($logo = SiteSetting::get('logo')) ? Storage::disk('public')->url($logo) : null)
            ->darkModeBrandLogo(fn () => ($logo = SiteSetting::get('logo_light') ?: SiteSetting::get('logo')) ? Storage::disk('public')->url($logo) : null)
            ->brandLogoHeight('3rem') // yatay logo + slogan; üst bar 4rem
            ->favicon(fn () => ($icon = SiteSetting::get('favicon')) ? Storage::disk('public')->url($icon) : null)
            ->colors([
                'primary' => Color::hex('#2D6A4F'),
            ])
            ->navigationGroups(['Mesajlar', 'İçerik', 'Ayarlar'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
