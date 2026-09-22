<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\DTOs\TrendPoint;
use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Analytics\Support\DashboardPeriod;
use App\Domain\Analytics\Support\PlantaGeneral;
use App\Filament\Widgets\Concerns\MuestraLosValoresEnBarras;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Horas de parada por Sección de planta (Generación eléctrica, Extracción,
 * Palmistería…), como el «Resumen horas de paros por sección» del Excel.
 */
class DowntimeBySectionWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    use MuestraLosValoresEnBarras;

    protected ?string $heading = 'Horas de Parada por Sección';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    public function getDescription(): ?string
    {
        $texto = 'En qué sección de la planta — '.DashboardPeriod::label($this->pageFilters).'.';
        $general = $this->horasDePlantaGeneral();

        // Lo apartado se dice, con sus horas. Una gráfica que esconde la barra más alta
        // sin avisar hace creer que ese tiempo no existió.
        return $general === null
            ? $texto
            : $texto.' No se grafican '.number_format($general, 1, ',', '.')
                .' h de «Planta general»: son paros de toda la planta, no de una sección.';
    }

    /** Las horas que quedan fuera, para poder decirlas. */
    private function horasDePlantaGeneral(): ?float
    {
        foreach ($this->puntos() as $punto) {
            if (PlantaGeneral::es($punto->label)) {
                return $punto->value;
            }
        }

        return null;
    }

    /** @return TrendPoint[] */
    private function puntos(): array
    {
        [$from, $to] = DashboardPeriod::resolve($this->pageFilters);

        return app(AnalyticsService::class)->downtimeBySection(Filament::getTenant()->id, $from, $to);
    }

    protected function getData(): array
    {
        // Fuera «Planta general»: es el cajón de los paros de toda la planta y aplasta a
        // las secciones de verdad —425,6 h contra 251,1 de Extracción, la siguiente—, que
        // son las que se pueden mirar y arreglar. Sus horas se dicen en el subtítulo.
        $points = array_values(array_filter(
            $this->puntos(),
            fn ($p): bool => ! PlantaGeneral::es($p->label),
        ));

        return [
            'datasets' => [
                [
                    'label' => 'Horas de parada',
                    'data' => array_map(fn ($p) => $p->value, $points),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                ],
            ],
            'labels' => array_map(fn ($p) => $p->label, $points),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
