<?php

namespace App\Filament\Widgets\Budget;

use App\Domain\Analytics\Services\BudgetTrackingService;
use App\Models\Plant;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Las cuatro cuentas del presupuesto del mes: cuánto se asignó, cuánto se lleva
 * gastado, cuánto falta y qué porcentaje va usado.
 */
class BudgetStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $plantId = $this->pageFilters['plant_id'] ?? null;
        $plant = $plantId !== null ? Plant::find($plantId) : Plant::orderBy('name')->first();

        if ($plant === null) {
            return [Stat::make('Presupuesto', 'Sin plantas registradas')];
        }

        $report = app(BudgetTrackingService::class)->monthlyReport(
            $plant,
            (int) ($this->pageFilters['year'] ?? now()->year),
            (int) ($this->pageFilters['month'] ?? now()->month),
        );

        $percent = $report['percent_used'];

        $spent = Stat::make('Gastado', self::money($report['total']))
            ->description(($report['expense_count']).' gasto(s) registrado(s)');

        if ($report['budget'] === null) {
            return [
                Stat::make('Presupuesto asignado', 'Sin asignar')
                    ->description('Usa «Asignar presupuesto» para fijar el techo del mes')
                    ->color('gray'),
                $spent,
            ];
        }

        return [
            Stat::make('Presupuesto asignado', self::money($report['budget'])),
            $spent->color(self::color($percent)),
            Stat::make('Restante', self::money($report['remaining']))
                ->description($report['is_over_budget'] ? 'Se pasó del presupuesto' : 'Disponible del mes')
                ->color($report['is_over_budget'] ? 'danger' : 'success'),
            Stat::make('% usado', ($percent ?? 0).'%')
                ->color(self::color($percent)),
        ];
    }

    private static function color(?float $percent): string
    {
        return match (true) {
            $percent === null => 'gray',
            $percent >= 100 => 'danger',
            $percent >= 80 => 'warning',
            default => 'success',
        };
    }

    private static function money(float $amount): string
    {
        return 'COP '.number_format($amount, 0);
    }
}
