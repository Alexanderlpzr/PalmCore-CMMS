<?php

namespace App\Filament\Widgets\Costs;

use App\Domain\Analytics\Services\MaintenanceCostReportService;
use App\Filament\Widgets\Costs\Concerns\ResolvesCostReportFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * En qué se fue la plata: mano de obra, repuestos y terceros. El desglose que
 * permite decir «el mes se disparó por repuestos», no solo «se disparó».
 */
class CostBreakdownWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use ResolvesCostReportFilters;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $plantId = $this->resolvePlantId();

        if ($plantId === null) {
            return [];
        }

        [$year, $month] = $this->resolvePeriod();
        $report = app(MaintenanceCostReportService::class)->monthlyReport(
            $this->tenantId(),
            $plantId,
            $year,
            $month,
        );

        return [
            Stat::make('Mano de obra', self::money($report['labor']))
                ->description(self::share($report['labor'], $report['total'])),
            Stat::make('Repuestos', self::money($report['parts']))
                ->description(self::share($report['parts'], $report['total'])),
            Stat::make('Terceros', self::money($report['external']))
                ->description(self::share($report['external'], $report['total'])),
        ];
    }

    private static function money(float $value): string
    {
        return 'COP '.number_format($value, 0, ',', '.');
    }

    private static function share(float $part, float $total): string
    {
        if ($total <= 0) {
            return 'Sin gasto en el mes';
        }

        return round($part / $total * 100, 1).'% del total';
    }
}
