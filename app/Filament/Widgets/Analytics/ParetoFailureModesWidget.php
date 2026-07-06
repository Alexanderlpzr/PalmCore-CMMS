<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Services\AnalyticsService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/**
 * Pareto of failures grouped by failure mode (bearing, seal, electrical…) — the
 * RCA-oriented complement to ParetoFailuresWidget (which groups by equipment).
 * Answers "what physically fails most across the plant" so effort targets the
 * dominant cause, not just the noisiest machine.
 */
class ParetoFailureModesWidget extends ChartWidget
{
    protected ?string $heading = 'Pareto por Modo de Falla';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 17;

    protected int|string|array $columnSpan = 'full';

    public function getDescription(): ?string
    {
        return 'Fallas no planificadas por modo (rodamiento, sello, eléctrico…) — últimos 12 meses. Se llena a medida que las OT correctivas clasifican su modo de falla.';
    }

    protected function getData(): array
    {
        $points = app(AnalyticsService::class)->paretoFailuresByMode(Filament::getTenant()->id);

        return [
            'datasets' => [
                [
                    'label' => 'Fallas',
                    'data' => array_map(fn ($p) => $p->value, $points),
                    'backgroundColor' => 'rgba(11, 110, 98, 0.75)',
                    'borderColor' => 'rgba(11, 110, 98, 1)',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => array_map(fn ($p) => $p->label, $points),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }
}
