<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\ExecutiveDashboardService;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExecutiveSummaryWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $summary = app(ExecutiveDashboardService::class)->summary(Filament::getTenant()->id);

        return [
            Stat::make('Disponibilidad', $summary['availability'].'%')
                ->color($summary['availability'] >= 90 ? 'success' : ($summary['availability'] >= 75 ? 'warning' : 'danger')),

            Stat::make('MTBF', number_format($summary['mtbf_hours'], 0).' h')
                ->description('Tiempo medio entre fallas'),

            Stat::make('MTTR', number_format($summary['mttr_hours'], 1).' h')
                ->description('Tiempo medio de reparación'),

            Stat::make('OT Abiertas', (string) $summary['open_work_orders'])
                ->description('Órdenes de trabajo activas'),

            Stat::make('Preventivos Vencidos', (string) $summary['overdue_preventives'])
                ->color($summary['overdue_preventives'] > 0 ? 'danger' : 'success'),

            Stat::make('Costo Mensual', 'COP '.number_format($summary['monthly_cost'], 0, ',', '.'))
                ->description('Mantenimiento total del período'),
        ];
    }
}
