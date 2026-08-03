<?php

namespace App\Filament\Widgets\Executive;

use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Collection;

/**
 * Eficiencia y disponibilidad de los meses ya cerrados — snapshot congelado por
 * SnapshotPlantKpisJob el día 1 de cada mes, así que esta serie nunca se mueve
 * por lecturas tardías del mes en curso (ese es PlantEfficiencyStatsWidget).
 *
 * Las dos series van juntas porque comparten unidad y se leen contra la misma
 * escala de 0 a 100: la distancia entre ellas es exactamente lo que el aseo y el
 * correctivo le cuestan a la planta. La productividad se grafica aparte
 * ({@see PlantMonthlyProductivityHistoryWidget}) — está en t/h, y montarla en
 * este eje haría parecer catastrófico un mes de 13,5 t/h.
 */
class PlantMonthlyEfficiencyHistoryWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Eficiencia y Disponibilidad — Meses Cerrados';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $months = $this->closedMonths();

        return [
            'datasets' => [
                [
                    'label' => 'Eficiencia (%)',
                    'data' => $months->map(fn (PlantMonthlyKpi $m) => $m->efficiency_percentage)->all(),
                    'backgroundColor' => 'rgba(26, 126, 66, 0.75)',
                    'borderColor' => 'rgba(26, 126, 66, 1)',
                ],
                [
                    'label' => 'Disponibilidad (%)',
                    'data' => $months->map(fn (PlantMonthlyKpi $m) => $m->availability_percentage)->all(),
                    'backgroundColor' => 'rgba(0, 56, 76, 0.75)',
                    'borderColor' => 'rgba(0, 56, 76, 1)',
                ],
            ],
            'labels' => $months->map(fn (PlantMonthlyKpi $m) => $m->periodLabel())->all(),
        ];
    }

    /** @return Collection<int, PlantMonthlyKpi> */
    private function closedMonths()
    {
        $plant = $this->selectedPlant();

        if ($plant === null) {
            return collect();
        }

        return PlantMonthlyKpi::where('plant_id', $plant->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
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
