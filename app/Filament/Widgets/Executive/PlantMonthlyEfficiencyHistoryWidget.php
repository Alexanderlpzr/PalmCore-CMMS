<?php

namespace App\Filament\Widgets\Executive;

use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Los meses ya cerrados — snapshot congelado por SnapshotPlantKpisJob el día 1
 * de cada mes, así que esta serie nunca se mueve por lecturas tardías del mes
 * en curso (ese es PlantEfficiencyStatsWidget, arriba).
 */
class PlantMonthlyEfficiencyHistoryWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Eficiencia — Meses Cerrados';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

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
                    'label' => 'Eficiencia (%)',
                    'data' => $months->map(fn (PlantMonthlyKpi $m) => $m->efficiency_percentage)->all(),
                    'backgroundColor' => $months->map(
                        fn (PlantMonthlyKpi $m) => match (true) {
                            $m->efficiency_percentage === null => 'rgba(148, 163, 184, 0.75)',
                            $m->efficiency_percentage >= 90 => 'rgba(16, 185, 129, 0.75)',
                            $m->efficiency_percentage >= 80 => 'rgba(245, 158, 11, 0.75)',
                            default => 'rgba(239, 68, 68, 0.75)',
                        }
                    )->all(),
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
                'y' => ['min' => 0, 'max' => 100],
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
