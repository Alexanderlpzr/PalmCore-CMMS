<?php

namespace App\Filament\Widgets\Budget;

use App\Domain\Analytics\Services\BudgetTrackingService;
use App\Models\Plant;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Avance del gasto: lo gastado acumulado semana a semana contra la línea (plana)
 * del presupuesto. Cuando la curva se acerca a la línea, se está por acabar el mes.
 */
class BudgetProgressChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Avance del gasto por semana';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 2;

    protected ?string $maxHeight = '280px';

    public function getDescription(): ?string
    {
        return 'Acumulado del mes contra el presupuesto asignado.';
    }

    protected function getData(): array
    {
        $plantId = $this->pageFilters['plant_id'] ?? null;
        $plant = $plantId !== null ? Plant::find($plantId) : Plant::orderBy('name')->first();

        if ($plant === null) {
            return ['datasets' => [], 'labels' => []];
        }

        $weekly = app(BudgetTrackingService::class)->monthlyReport(
            $plant,
            (int) ($this->pageFilters['year'] ?? now()->year),
            (int) ($this->pageFilters['month'] ?? now()->month),
        )['weekly'];

        return [
            'datasets' => [
                [
                    'label' => 'Gastado (acumulado)',
                    'data' => $weekly['accumulated'],
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Presupuesto',
                    'data' => $weekly['budget_line'],
                    'borderColor' => 'rgba(239, 68, 68, 0.9)',
                    'borderDash' => [6, 4],
                    'pointRadius' => 0,
                    'fill' => false,
                ],
            ],
            'labels' => $weekly['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
