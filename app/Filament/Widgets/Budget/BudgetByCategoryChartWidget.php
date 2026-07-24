<?php

namespace App\Filament\Widgets\Budget;

use App\Domain\Analytics\Services\BudgetTrackingService;
use App\Domain\Maintenance\Enums\ExpenseCategory;
use App\Models\Plant;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * En qué se invirtió el presupuesto: el gasto del mes repartido por concepto.
 */
class BudgetByCategoryChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'En qué se invirtió';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $plantId = $this->pageFilters['plant_id'] ?? null;
        $plant = $plantId !== null ? Plant::find($plantId) : Plant::orderBy('name')->first();

        if ($plant === null) {
            return ['datasets' => [], 'labels' => []];
        }

        $byCategory = app(BudgetTrackingService::class)->monthlyReport(
            $plant,
            (int) ($this->pageFilters['year'] ?? now()->year),
            (int) ($this->pageFilters['month'] ?? now()->month),
        )['by_category'];

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($byCategory as $value => $amount) {
            $category = ExpenseCategory::from($value);
            $labels[] = $category->label();
            $data[] = $amount;
            $colors[] = $category->chartColor();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Gasto',
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
