<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Support\DashboardPeriod;
use App\Domain\Analytics\Support\PlantaGeneral;
use App\Domain\Assets\Services\DowntimeService;
use App\Filament\Widgets\Concerns\MuestraLosValoresEnBarras;
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
    use MuestraLosValoresEnBarras;

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

        // Fuera el comodín «PLANTA GENERAL»: es el equipo ficticio donde se anotan los
        // paros de toda la planta, y en una gráfica de «dónde se pierden las horas» no se
        // puede ir a arreglar. Se quita antes de recortar a doce, para que su hueco lo
        // ocupe un equipo de verdad.
        $rows = array_values(array_filter(
            app(DowntimeService::class)->lostHoursByEquipment($plant->id, $from, $to)['equipment'],
            fn (array $r): bool => ! PlantaGeneral::es($r['name'] ?? null) && ! PlantaGeneral::es($r['code'] ?? null),
        ));

        $rows = array_slice($rows, 0, 12);

        return [
            'datasets' => [
                [
                    'label' => 'Horas de parada',
                    'data' => array_map(fn (array $r): float => $r['hours'], $rows),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                ],
            ],
            // El nombre y no el código: «Tricanter» se identifica de un vistazo y
            // «A06CLA.12.01» hay que descifrarlo. El código sigue siendo la clave
            // para buscar en campo, pero una gráfica se lee, no se consulta.
            'labels' => array_map(fn (array $r): string => $r['name'] ?? $r['code'], $rows),
        ];
    }

    /** Barras horizontales: los nombres de equipo se leen mejor así, como en el Excel. */
    protected function barrasHorizontales(): bool
    {
        return true;
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
