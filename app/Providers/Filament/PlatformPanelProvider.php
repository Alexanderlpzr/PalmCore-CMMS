<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Http\Middleware\EnsureSuperAdmin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PlatformPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('platform')
            ->path('platform')
            ->login()
            ->brandName('Fronda · Plataforma')
            ->brandLogo(secure_asset('images/logo.png'))
            ->brandLogoHeight('4rem')
            ->favicon(secure_asset('images/isotipo.png'))
            // Sin esto, las clases de Tailwind escritas a mano en las páginas de
            // plataforma (dashboard, respaldos, colas, logs) nunca se compilaban: la
            // hoja de estilos por defecto de Filament solo incluye las utilidades que
            // el propio Filament usa. El panel se veía como texto plano sin tarjetas,
            // sin colores ni bordes, porque el CSS que las respaldaba no existía. El
            // panel `admin` ya resolvió este mismo problema — ver su propio
            // ->viteTheme() en AdminPanelProvider.
            ->viteTheme('resources/css/filament/platform/theme.css')
            ->colors([
                'primary' => Color::hex('#7c3aed'),
                'success' => Color::Emerald,
                'gray' => Color::Slate,
                'info' => Color::Blue,
                'warning' => Color::Amber,
                'danger' => Color::Red,
            ])
            // Local data-URI initials avatars — keeps the strict CSP (no
            // ui-avatars.com request) consistent with the admin panel.
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->navigationGroups([
                NavigationGroup::make('Empresas'),
                NavigationGroup::make('Contenido'),
                NavigationGroup::make('Suscripciones'),
                NavigationGroup::make('Integraciones'),
                NavigationGroup::make('Observabilidad'),
                NavigationGroup::make('Sistema'),
            ])
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn (): string => '<div class="p-2"><a href="/admin" class="block text-xs text-center text-emerald-600 hover:text-emerald-800 font-medium py-2 px-3 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors">← Panel Admin</a></div>',
            )
            ->discoverResources(in: app_path('Filament/Platform/Resources'), for: 'App\Filament\Platform\Resources')
            ->discoverPages(in: app_path('Filament/Platform/Pages'), for: 'App\Filament\Platform\Pages')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureSuperAdmin::class,
            ]);
    }
}
