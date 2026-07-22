<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\CheckTenantSubscription;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\SyncSpatieTeamId;
use App\Models\Tenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
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
            ->login(Login::class)
            ->brandName('Fronda CMMS')
            ->brandLogo(secure_asset('images/logo.png'))
            ->brandLogoHeight('4rem')
            ->favicon(secure_asset('images/isotipo.png'))
            // Custom theme (HOME-2.1) — compiles the bespoke Tailwind utilities
            // used by the Inicio portal and custom resource views, which the
            // default Filament stylesheet does not ship.
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Permanent impersonation banner — rendered on every panel page while
            // a Super Admin is impersonating another user (ADMIN-2).
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): View => view('filament.impersonation-banner'),
            )
            // Subscription status banner — shown for trial, read_only, and suspended tenants.
            // Healthy active tenants see no banner; expiring-soon uses a Filament notification.
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): View => view('filament.subscription-banner'),
            )
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
            // Without this, every "reporte listo para descargar" notification
            // sent via ->sendToDatabase() (Excel/PDF export jobs, webhooks,
            // etc.) is stored correctly but has no UI surface at all — no bell
            // icon, no polling, nothing. Users saw the initial "Generando..."
            // toast and then nothing ever again, even though the file existed.
            ->databaseNotifications()
            // Orden maestro del menú (UX-3). Ordenado por frecuencia de uso
            // operativo real: el ciclo de mantenimiento diario primero, la
            // administración esporádica al final.
            ->navigationGroups([
                NavigationGroup::make('Portal de Inicio'),
                NavigationGroup::make('Mantenimiento'),
                NavigationGroup::make('Centro de Alertas'),
                NavigationGroup::make('Indicadores'),
                NavigationGroup::make('Estructura Operativa'),
                NavigationGroup::make('Usuarios & Acceso'),
                NavigationGroup::make('Automatizaciones'),
            ])
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn (): string => auth()->user()?->is_super_admin
                    ? '<div class="p-2"><a href="/platform" class="block text-xs text-center text-violet-600 hover:text-violet-800 font-medium py-2 px-3 bg-violet-50 hover:bg-violet-100 rounded-lg transition-colors">→ Panel de Plataforma</a></div>'
                    : '',
            )
            ->tenant(Tenant::class, slugAttribute: 'slug')
            ->tenantMiddleware([
                SyncSpatieTeamId::class,
                CheckTenantSubscription::class,
            ], isPersistent: true)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            // Inicio (portal de entrada) y Dashboard (analítica) se auto-descubren
            // desde App\Filament\Pages — Inicio ocupa la raíz, Dashboard vive en /dashboard.
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
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
                EnforceTwoFactor::class,
            ]);
    }
}
