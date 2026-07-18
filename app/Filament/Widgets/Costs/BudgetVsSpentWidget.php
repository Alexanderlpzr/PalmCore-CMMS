<?php

namespace App\Filament\Widgets\Costs;

use App\Domain\Analytics\Services\MaintenanceCostReportService;
use App\Filament\Widgets\Costs\Concerns\ResolvesCostReportFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * La respuesta que busca la gerencia: cuánto se asignó, cuánto se gastó, cuánto
 * queda — y en rojo si el mes se pasó del presupuesto.
 */
class BudgetVsSpentWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use ResolvesCostReportFilters;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $plantId = $this->resolvePlantId();

        if ($plantId === null) {
            return [Stat::make('Presupuesto', 'Sin plantas registradas')];
        }

        [$year, $month] = $this->resolvePeriod();
        $report = app(MaintenanceCostReportService::class)->monthlyReport(
            $this->tenantId(),
            $plantId,
            $year,
            $month,
        );

        $spent = Stat::make('Gastado en el mes', self::money($report['total']))
            ->description($report['work_order_count'].' orden(es) completada(s)');

        if ($report['budget'] === null) {
            return [
                Stat::make('Presupuesto asignado', 'Sin asignar')
                    ->description('Fíjalo en «Presupuestos» para medir el gasto')
                    ->color('gray'),
                $spent,
            ];
        }

        $remaining = $report['remaining'];
        $over = $report['is_over_budget'];

        return [
            Stat::make('Presupuesto asignado', self::money($report['budget'])),
            $spent->color($report['percent_used'] >= 100 ? 'danger' : ($report['percent_used'] >= 80 ? 'warning' : 'success')),
            Stat::make($over ? 'Sobregiro' : 'Disponible', self::money(abs($remaining)))
                ->description($report['percent_used'].'% del presupuesto usado')
                ->color($over ? 'danger' : 'success'),
        ];
    }

    private static function money(float $value): string
    {
        return 'COP '.number_format($value, 0, ',', '.');
    }
}
