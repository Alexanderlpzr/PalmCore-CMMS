<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Analytics\Support\DashboardPeriod;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FailuresByMonthWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Fallas por Mes';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'half';

    public function getDescription(): ?string
    {
        $period = DashboardPeriod::label($this->pageFilters);

        return "Cantidad de fallas no planificadas — {$period}.";
    }

    protected function getData(): array
    {
        [$from, $to] = DashboardPeriod::resolve($this->pageFilters);
        $points = app(AnalyticsService::class)->failuresByMonth(Filament::getTenant()->id, $from, $to);

        return [
            'datasets' => [
                [
                    'label' => 'Fallas',
                    'data' => array_map(fn ($p) => $p->value, $points),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.75)',
                    'borderColor' => 'rgba(239, 68, 68, 1)',
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
}
