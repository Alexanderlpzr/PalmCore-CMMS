<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\Analytics\Services\BudgetTrackingService;
use App\Filament\Resources\MaintenanceBudgets\MaintenanceBudgetResource;
use App\Filament\Widgets\ListKpis;
use App\Models\Plant;
use App\Support\FrondaPalette;
use Carbon\CarbonImmutable;

/**
 * Encima de los presupuestos: el del mes en curso —asignado, gastado, lo que queda y el
 * porcentaje usado— sumando las plantas, y lo gastado en los últimos meses. Las cuentas
 * son las de la página de Presupuesto.
 */
class MaintenanceBudgetKpis extends ListKpis
{
    protected function kpis(): array
    {
        $month = $this->monthTotals(CarbonImmutable::now()->startOfMonth());
        $percent = $month['budget'] > 0 ? $month['spent'] * 100 / $month['budget'] : null;
        $remaining = $month['budget'] - $month['spent'];

        return [
            [
                'label' => 'Asignado este mes',
                'value' => $month['budget'] > 0 ? '$ '.number_format($month['budget'], 0, ',', '.') : 'Sin asignar',
                'detail' => $month['budget'] > 0 ? 'Todas las plantas' : 'Use «Nuevo presupuesto» para fijar el techo del mes',
                'icon' => 'heroicon-o-banknotes',
                'tone' => $month['budget'] > 0 ? 'neutral' : 'warning',
            ],
            [
                'label' => 'Gastado',
                'value' => '$ '.number_format($month['spent'], 0, ',', '.'),
                'detail' => 'Cargado al presupuesto del mes',
                'icon' => 'heroicon-o-receipt-percent',
                'percent' => $percent,
                'color' => match (true) {
                    $percent === null => FrondaPalette::Brand[600],
                    $percent >= 100 => '#dc2626',
                    $percent >= 80 => '#f59e0b',
                    default => FrondaPalette::Brand[600],
                },
                'tone' => match (true) {
                    $percent === null => 'neutral',
                    $percent >= 100 => 'danger',
                    $percent >= 80 => 'warning',
                    default => 'ok',
                },
            ],
            [
                'label' => $remaining < 0 ? 'Excedido' : 'Disponible',
                'value' => '$ '.number_format(abs($remaining), 0, ',', '.'),
                'detail' => $remaining < 0 ? 'Se pasó del presupuesto del mes' : 'Lo que queda del mes',
                'icon' => 'heroicon-o-scale',
                'tone' => $remaining < 0 ? 'danger' : 'ok',
            ],
            [
                'label' => '% usado',
                'value' => $percent === null ? '—' : number_format($percent, 0, ',', '.').' %',
                'detail' => 'Del presupuesto asignado',
                'icon' => 'heroicon-o-chart-pie',
                'tone' => $percent !== null && $percent >= 100 ? 'danger' : ($percent !== null && $percent >= 80 ? 'warning' : 'neutral'),
            ],
        ];
    }

    protected function bars(): ?array
    {
        $months = collect(range(5, 0))->map(fn (int $i): CarbonImmutable => CarbonImmutable::now()->startOfMonth()->subMonthsNoOverflow($i));
        $totals = $months->map(fn (CarbonImmutable $m): array => $this->monthTotals($m));

        return [
            'title' => 'Gastado por mes (millones de pesos)',
            'labels' => $months->map(fn (CarbonImmutable $m): string => ucfirst($m->locale('es')->translatedFormat('M')))->all(),
            'series' => [[
                'label' => 'Gastado',
                'color' => FrondaPalette::Petrol[600],
                'values' => $totals->map(fn (array $t): float => round($t['spent'] / 1_000_000, 1))->all(),
            ]],
        ];
    }

    /** @return array{budget: float, spent: float} */
    private function monthTotals(CarbonImmutable $month): array
    {
        $service = app(BudgetTrackingService::class);
        $budget = 0.0;
        $spent = 0.0;

        foreach (Plant::query()->get() as $plant) {
            $report = $service->monthlyReport($plant, $month->year, $month->month);
            $budget += (float) ($report['budget'] ?? 0);
            $spent += (float) $report['total'];
        }

        return ['budget' => $budget, 'spent' => $spent];
    }

    protected function listUrl(array $parameters = []): string
    {
        return MaintenanceBudgetResource::getUrl('index', $parameters);
    }
}
