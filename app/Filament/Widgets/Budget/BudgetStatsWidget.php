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

    protected string $view = 'filament.widgets.fronda-stats';

    /** @var array<string, mixed>|null */
    private ?array $report = null;

    /**
     * La tarjeta grande: lo gastado en el mes, con la barra contra lo asignado.
     *
     * @return array<string, mixed>|null
     */
    public function frondaHero(): ?array
    {
        $report = $this->report();

        if ($report === null) {
            return null;
        }

        $month = ucfirst(now()->setDate((int) ($this->pageFilters['year'] ?? now()->year), (int) ($this->pageFilters['month'] ?? now()->month), 1)->locale('es')->translatedFormat('F \d\e Y'));
        $percent = $report['percent_used'];

        return [
            'label' => 'Gastado en mantenimiento · '.$month,
            'value' => (float) $report['total'],
            'prefix' => '$ ',
            'icon' => 'heroicon-o-banknotes',
            'badge' => $report['budget'] === null ? 'Sin presupuesto asignado' : ($report['is_over_budget'] ? 'Se pasó del presupuesto' : ($percent ?? 0).' % usado'),
            'percent' => $report['budget'] === null ? null : min(100, (float) ($percent ?? 0)),
            'progressLabel' => $report['budget'] === null ? null : 'Del presupuesto del mes',
            'empty' => $report['budget'] === null ? 'Use «Asignar presupuesto» para fijar el techo del mes y ver cuánto queda.' : null,
            'stats' => $report['budget'] === null ? [] : [
                ['label' => 'Presupuesto asignado', 'value' => self::money($report['budget'])],
                ['label' => $report['is_over_budget'] ? 'Se pasó por' : 'Restante', 'value' => self::money(abs($report['remaining']))],
            ],
            'replaces' => ['Presupuesto asignado', 'Gastado', 'Restante', '% usado'],
        ];
    }

    protected function getStats(): array
    {
        $report = $this->report();

        if ($report === null) {
            return [Stat::make('Presupuesto', 'Sin plantas registradas')];
        }

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

    /** @return array<string, mixed>|null el informe del mes elegido, una sola vez por pintada */
    private function report(): ?array
    {
        $plantId = $this->pageFilters['plant_id'] ?? null;
        $plant = $plantId !== null ? Plant::find($plantId) : Plant::orderBy('name')->first();

        if ($plant === null) {
            return null;
        }

        return $this->report ??= app(BudgetTrackingService::class)->monthlyReport(
            $plant,
            (int) ($this->pageFilters['year'] ?? now()->year),
            (int) ($this->pageFilters['month'] ?? now()->month),
        );
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
        return '$ '.number_format($amount, 0, ',', '.');
    }
}
