<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Analytics\Support\DashboardPeriod;
use App\Filament\Widgets\Concerns\MuestraLosValores;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Las horas que son de mantenimiento, abiertas por causa: mantenimiento programado,
 * falla mecánica, falla eléctrica, instrumentación.
 *
 * Sustituye al gráfico de «Tipo I», que repartía las horas entre Programada /
 * Mantenimiento / Operativa — cuatro palabras que dicen **quién paró la línea** y no qué
 * se rompió, y que por eso no se podían accionar: ver «Operativa: 260 h» no le dice a
 * nadie qué arreglar.
 *
 * Va por causa física y no por el Tipo I escrito en la planilla. En El Pajuil hay 141,6 h
 * de falla mecánica anotadas como «Operativa» frente a 164,9 h anotadas como
 * «Mantenimiento»: clasificar por Tipo I escondería casi la mitad de las fallas mecánicas.
 */
class DowntimeMaintenanceBreakdownWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    use MuestraLosValores;

    /** Un color por causa, estable: el mismo tono significa lo mismo en los dos gráficos. */
    private const COLORS = [
        'Mantenimiento programado' => 'rgba(59, 130, 246, 0.85)',
        'Falla mecánica' => 'rgba(239, 68, 68, 0.85)',
        'Falla eléctrica' => 'rgba(234, 179, 8, 0.85)',
        'Instrumentación' => 'rgba(168, 85, 247, 0.85)',
    ];

    protected ?string $heading = 'Mantenimiento: en qué se fueron las horas';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    public function getDescription(): ?string
    {
        return 'Solo lo que le toca a mantenimiento, por causa física — '
            .DashboardPeriod::label($this->pageFilters).'. '
            .'Arranque y apagado de planta no entran: son maniobras de operación, aunque la planilla las marque como programadas.';
    }

    protected function getData(): array
    {
        [$from, $to] = DashboardPeriod::resolve($this->pageFilters);
        $points = app(AnalyticsService::class)->downtimeMaintenanceByReason(Filament::getTenant()->id, $from, $to);

        return [
            'datasets' => [
                [
                    'label' => 'Horas de parada',
                    'data' => array_map(fn ($p) => $p->value, $points),
                    'backgroundColor' => array_map(
                        fn ($p) => self::COLORS[$p->label] ?? 'rgba(100, 116, 139, 0.85)',
                        $points,
                    ),
                ],
            ],
            'labels' => array_map(fn ($p) => $p->label, $points),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
