<?php

namespace App\Filament\Widgets\Reliability;

use App\Domain\Analytics\Services\AnalyticsService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Leading maintenance-management indicators (as opposed to the lagging
 * reliability KPIs): are we keeping up with preventive maintenance, and is the
 * work mix trending proactive? These are the numbers an engineer acts on to
 * prevent failures, not just measure them after the fact.
 */
class MaintenanceComplianceWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $service = app(AnalyticsService::class);
        $tenantId = Filament::getTenant()->id;

        $compliance = $service->preventiveCompliance($tenantId);
        $mix = $service->plannedVsCorrective($tenantId);

        return [
            $this->complianceStat($compliance),
            $this->overdueStat($compliance),
            $this->mixStat($mix),
        ];
    }

    /**
     * @param  array{total: int, on_schedule: int, overdue: int, compliance: ?float}  $compliance
     */
    private function complianceStat(array $compliance): Stat
    {
        if ($compliance['compliance'] === null) {
            return Stat::make('Cumplimiento de Preventivo', 'Sin planes activos')
                ->description('Crea planes de mantenimiento para medir la adherencia')
                ->color('gray');
        }

        $pct = $compliance['compliance'];

        return Stat::make('Cumplimiento de Preventivo', $pct.'%')
            ->description("{$compliance['on_schedule']} al día · {$compliance['overdue']} vencidos")
            ->descriptionIcon($pct >= 90 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
            ->color($pct >= 90 ? 'success' : ($pct >= 70 ? 'warning' : 'danger'));
    }

    /**
     * @param  array{total: int, on_schedule: int, overdue: int, compliance: ?float}  $compliance
     */
    private function overdueStat(array $compliance): Stat
    {
        $overdue = $compliance['overdue'];

        return Stat::make('Preventivos Vencidos', (string) $overdue)
            ->description($overdue === 0 ? 'Todo el plan al día' : 'Requieren programarse cuanto antes')
            ->color($overdue === 0 ? 'success' : ($overdue <= 3 ? 'warning' : 'danger'));
    }

    /**
     * @param  array{preventive: int, corrective: int, total: int, preventive_pct: ?float}  $mix
     */
    private function mixStat(array $mix): Stat
    {
        if ($mix['preventive_pct'] === null) {
            return Stat::make('Preventivo vs Correctivo', 'Sin OT cerradas')
                ->description('Últimos 12 meses')
                ->color('gray');
        }

        $pct = $mix['preventive_pct'];

        return Stat::make('Preventivo vs Correctivo', $pct.'% preventivo')
            ->description("{$mix['preventive']} preventivas · {$mix['corrective']} correctivas (12 meses)")
            ->descriptionIcon('heroicon-m-wrench-screwdriver')
            ->color($pct >= 70 ? 'success' : ($pct >= 50 ? 'warning' : 'danger'));
    }
}
