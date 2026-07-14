<?php

namespace App\Filament\Platform\Pages;

use App\Domain\Platform\Services\SystemHealthService;
use App\Domain\Platform\Services\TenantHealthService;
use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * La sala de máquinas.
 *
 * Responde dos preguntas, y solo esas dos: ¿está viva la plataforma ahora mismo?, y
 * ¿está viva cada empresa que la usa? Todo lo demás —suscripciones, contenido— tiene
 * su propia pantalla.
 */
class PlatformDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.platform.dashboard';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Actualizar')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => null),
        ];
    }

    public function getViewData(): array
    {
        $health = app(SystemHealthService::class);

        return [
            'totalTenants' => Tenant::withoutGlobalScopes()->count(),
            'activeTenants' => Tenant::withoutGlobalScopes()
                ->where('subscription_status', SubscriptionStatus::Active)->count(),
            'trialTenants' => Tenant::withoutGlobalScopes()
                ->where('subscription_status', SubscriptionStatus::Trial)->count(),
            'suspendedTenants' => Tenant::withoutGlobalScopes()
                ->where('subscription_status', SubscriptionStatus::Suspended)->count(),

            'overall' => $health->overallStatus(),
            'checks' => $health->checks(),
            'tenants' => app(TenantHealthService::class)->overview(),
        ];
    }
}
