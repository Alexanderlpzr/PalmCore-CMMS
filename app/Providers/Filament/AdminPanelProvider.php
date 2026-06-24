<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Http\Middleware\CheckTenantSubscription;
use App\Http\Middleware\SyncSpatieTeamId;
use App\Models\Tenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
            ->brandName('Fronda CMMS')
            ->brandLogo(secure_asset('images/logo.png'))
            ->brandLogoHeight('4rem')
            ->favicon(secure_asset('images/isotipo.png'))
            // Brand + semantic colors aligned with the shared Fronda tokens
            // (resources/css/app.css · resources/js/shared/design.js) so Filament,
            // Ops and Mobile read as one product. Fronda green = #059669.
            ->colors([
                'primary' => Color::hex('#059669'),
                'success' => Color::Emerald,
                'gray' => Color::Slate,
                'info' => Color::Blue,
                'warning' => Color::Amber,
                'danger' => Color::Red,
            ])
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->navigationGroups([
                NavigationGroup::make('Centro de Alertas'),
                NavigationGroup::make('Mantenimiento'),
                NavigationGroup::make('Gestión de Activos'),
                NavigationGroup::make('Inventario'),
                NavigationGroup::make('Estructura Operativa'),
                NavigationGroup::make('Empresa'),
                NavigationGroup::make('Usuarios & Acceso'),
                NavigationGroup::make('Integraciones'),
                NavigationGroup::make('Configuración'),
            ])
            ->tenant(Tenant::class, slugAttribute: 'slug')
            ->tenantMiddleware([
                SyncSpatieTeamId::class,
                CheckTenantSubscription::class,
            ], isPersistent: true)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
            ]);
    }
}
