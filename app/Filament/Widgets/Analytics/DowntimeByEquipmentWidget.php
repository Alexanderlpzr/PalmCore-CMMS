<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Support\DashboardPeriod;
use App\Domain\Assets\Services\DowntimeService;
use App\Models\Plant;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

/**
 * Horas de parada por equipo, peor primero — el «Indicador de paradas por
 * equipos/horas» del Excel. Sale del mismo cálculo de unión de paros que evita
 * cobrar dos veces la misma hora ({@see DowntimeService::lostHoursByEquipment}).
 */
class DowntimeByEquipmentWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Horas de Parada por Equipo';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 14;

    protected int|string|array $columnSpan = 2;

    protected ?string $maxHeight = '320px';

    public function getDescription(): ?string
    {
        return 'Dónde se pierden las horas — '.DashboardPeriod::label($this->pageFilters).'.';
    }

    protected function getData(): array
    {
        $plant = $this->selectedPlant();

        if ($plant === null) {
            return ['datasets' => [], 'labels' => []];
        }

        [$from, $to] = DashboardPeriod::resolve($this->pageFilters);
        $from = $from !== null ? Carbon::parse($from)->startOfMonth() : Carbon::now()->startOfMonth();
        $to = $to !== null ? Carbon::parse($to)->endOfMonth() : Carbon::now()->endOfMonth();

        $rows = array_slice(
            app(DowntimeService::class)->lostHoursByEquipment($plant->id, $from, $to)['equipment'],
            0,
            12,
        );

        return [
            'datasets' => [
                [
                    'label' => 'Horas de parada',
                    'data' => array_map(fn (array $r): float => $r['hours'], $rows),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                ],
            ],
            'labels' => array_map(fn (array $r): string => $r['code'] ?? $r['name'], $rows),
        ];
    }

    protected function getOptions(): array
    {
        // Barras horizontales: los nombres de equipo se leen mejor así, como en el Excel.
        return ['indexAxis' => 'y'];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    private function selectedPlant(): ?Plant
    {
        $plantId = $this->pageFilters['plant_id'] ?? null;

        return $plantId !== null ? Plant::find($plantId) : Plant::orderBy('name')->first();
    }
}
