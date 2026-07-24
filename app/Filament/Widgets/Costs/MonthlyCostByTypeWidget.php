<?php

namespace App\Filament\Widgets\Costs;

use App\Domain\Analytics\Services\MaintenanceCostReportService;
use App\Filament\Widgets\Costs\Concerns\ResolvesCostReportFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * El gasto del mes repartido por tipo de mantenimiento. Un mes dominado por
 * correctivo es plata apagando incendios; uno dominado por preventivo es plata
 * comprando que no se prendan.
 */
class MonthlyCostByTypeWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    use ResolvesCostReportFilters;

    protected ?string $heading = 'Gasto por Tipo';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 31;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $plantId = $this->resolvePlantId();

        if ($plantId === null) {
            return ['datasets' => [], 'labels' => []];
        }

        [$year, $month] = $this->resolvePeriod();
        $byType = app(MaintenanceCostReportService::class)->monthlyReport(
            $this->tenantId(),
            $plantId,
            $year,
            $month,
        )['by_type'];

        return [
            'datasets' => [
                [
                    'label' => 'Gasto',
                    'data' => [
                        $byType['corrective'],
                        $byType['preventive'],
                        $byType['predictive'],
                        $byType['other'],
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
