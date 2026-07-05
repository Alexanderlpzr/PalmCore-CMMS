<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Analytics\Support\DashboardPeriod;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class MttrTrendWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Tendencia MTTR';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 13;

    protected int|string|array $columnSpan = 'half';

    public function getDescription(): ?string
    {
        $period = DashboardPeriod::label($this->pageFilters);

        return "Tiempo Medio de Reparación (horas) — {$period}, calculado mensualmente. Gaps indican meses sin fallas.";
    }

    protected function getData(): array
    {
        [$from, $to] = DashboardPeriod::resolve($this->pageFilters);
        $points = app(AnalyticsService::class)->mttrTrend(Filament::getTenant()->id, $from, $to);

        return [
            'datasets' => [
                [
                    'label' => 'MTTR (h)',
                    'data' => array_map(fn ($p) => $p->value, $points),
                    'borderColor' => 'rgba(168, 85, 247, 1)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.15)',
                    'tension' => 0.3,
                    'fill' => true,
                    'pointBackgroundColor' => 'rgba(168, 85, 247, 1)',
                    'spanGaps' => false,
                ],
            ],
            'labels' => array_map(fn ($p) => $p->label, $points),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
