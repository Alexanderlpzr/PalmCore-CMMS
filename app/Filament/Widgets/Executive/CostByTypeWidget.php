<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\ExecutiveDashboardService;
use App\Domain\Analytics\Support\DashboardPeriod;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CostByTypeWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Costos por Tipo';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'half';

    public function getDescription(): ?string
    {
        $period = DashboardPeriod::labelForSnapshot($this->pageFilters);

        return "Costo real de órdenes de trabajo completadas — {$period}, por tipo de mantenimiento.";
    }

    protected function getData(): array
    {
        [$from, $to] = DashboardPeriod::resolve($this->pageFilters);
        $costs = app(ExecutiveDashboardService::class)->costs(Filament::getTenant()->id, $from, $to);

        return [
            'datasets' => [
                [
                    'label' => 'Costo',
                    'data' => [
                        $costs['corrective'],
                        $costs['preventive'],
                        $costs['predictive'],
                        $costs['other'],
                    ],
                    'backgroundColor' => [
                        'rgba(248, 113, 113, 0.75)',
                        'rgba(16, 185, 129, 0.75)',
                        'rgba(99, 102, 241, 0.75)',
                        'rgba(148, 163, 184, 0.75)',
                    ],
                ],
            ],
            'labels' => ['Correctivo', 'Preventivo', 'Predictivo', 'Otro'],
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
        ];
    }
}
