<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\CheckTenantSubscription;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\SyncSpatieTeamId;
use App\Models\Tenant;
use App\Support\FrondaPalette;
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
            ->brandName('PAJUIL CMMS')
            ->brandLogo(branding_asset('images/logo.png'))
            ->brandLogoHeight('4rem')
            ->favicon(branding_asset('images/isotipo.png'))
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
            // Paleta alineada con los tokens compartidos (app/Support/FrondaPalette.php
            // · resources/css/fronda-tokens.css) para que Filament, Ops y Mobile se
            // lean como un solo producto. El primario es el verde exacto del logo.
            //
            // Se pasa la rampa completa en vez de Color::hex(): ese helper solo toma
            // el TONO del color y le impone la curva de luminosidad de Filament, así
            // que el botón nunca habría sido el verde del logo. `success` comparte la
            // rampa del primario a propósito — verde de marca y verde de éxito son el
            // mismo color, y tener dos verdes distintos era parte del ruido visual.
            ->colors([
                'primary' => FrondaPalette::Brand,
                'success' => FrondaPalette::Brand,
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
            //
            // La lista tiene que nombrar **todos** los grupos que use algún recurso.
            // Filament pinta los no declarados *antes* de los declarados, así que un
            // grupo olvidado no cae al final: se cuela arriba del todo y deshace este
            // orden. Es lo que pasó con «Gestión de Activos», que quedó por delante de
            // Mantenimiento porque aquí figuraba «Estructura Operativa», un nombre que
            // ningún recurso usaba ya. Hay un test que vigila que no vuelva a pasar.
            ->navigationGroups([
                NavigationGroup::make('Portal de Inicio'),
                NavigationGroup::make('Mantenimiento'),
                NavigationGroup::make('Centro de Alertas'),
                NavigationGroup::make('Indicadores'),
                NavigationGroup::make('Gestión de Activos'),
                NavigationGroup::make('Inventario'),
                NavigationGroup::make('Usuarios & Acceso'),
                NavigationGroup::make('Automatizaciones'),
                NavigationGroup::make('Integraciones'),
                NavigationGroup::make('Configuración'),
                NavigationGroup::make('Sistema'),
            ])
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                // El enlace se pinta con el color del panel al que lleva (petróleo),
                // no con un violeta que no está en el logo.
                fn (): string => auth()->user()?->is_super_admin
                    ? '<div class="p-2"><a href="/platform" class="block text-xs text-center text-petrol-700 hover:text-petrol-800 dark:text-petrol-200 dark:hover:text-white font-medium py-2 px-3 bg-petrol-50 hover:bg-petrol-100 dark:bg-white/5 dark:hover:bg-white/10 rounded-lg transition-colors">→ Panel de Plataforma</a></div>'
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
