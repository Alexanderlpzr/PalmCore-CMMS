<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Services\AnalyticsService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class ParetoFailuresWidget extends ChartWidget
{
    protected ?string $heading = 'Pareto de Fallas';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 21;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '280px';

    public function getDescription(): ?string
    {
        return 'Top 10 equipos con más fallas no planificadas — últimos 12 meses (desde eventos reales, no KPI snapshot).';
    }

    protected function getData(): array
    {
        $points = app(AnalyticsService::class)->paretoFailures(Filament::getTenant()->id);

        return [
            'datasets' => [
                [
                    'label' => 'Fallas',
                    'data' => array_map(fn ($p) => $p->value, $points),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.75)',
                    'borderColor' => 'rgba(245, 158, 11, 1)',
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
