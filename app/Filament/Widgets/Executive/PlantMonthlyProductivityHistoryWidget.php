<?php

namespace App\Filament\Widgets\Executive;

use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Productividad de los meses cerrados, en toneladas por hora prensable.
 *
 * Va en su propia gráfica y no junto a la eficiencia porque la unidad es otra:
 * en un eje de 0 a 100 un mes excelente de 13,5 t/h se vería como una barra
 * aplastada contra el suelo. El eje arranca en cero igual —una productividad no
 * se compara contra una base recortada— pero su techo lo pone el propio dato.
 */
class PlantMonthlyProductivityHistoryWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Productividad — Meses Cerrados';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $plant = $this->selectedPlant();

        $months = $plant !== null
            ? PlantMonthlyKpi::where('plant_id', $plant->id)
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->limit(12)
                ->get()
                ->reverse()
                ->values()
            : collect();

        return [
            'datasets' => [
                [
                    'label' => 'Productividad (t/h)',
                    'data' => $months->map(fn (PlantMonthlyKpi $m) => $m->productivity_tons_per_hour)->all(),
                    'backgroundColor' => 'rgba(26, 126, 66, 0.75)',
                    'borderColor' => 'rgba(26, 126, 66, 1)',
                ],
            ],
            'labels' => $months->map(fn (PlantMonthlyKpi $m) => $m->periodLabel())->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }

    private function selectedPlant(): ?Plant
    {
        $plantId = $this->pageFilters['plant_id'] ?? null;

        if ($plantId !== null) {
            return Plant::find($plantId);
        }

        return Plant::orderBy('name')->first();
    }
}
