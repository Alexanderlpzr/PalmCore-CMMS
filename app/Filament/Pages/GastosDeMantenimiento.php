<?php

namespace App\Filament\Pages;

use App\Domain\Reports\Excel\MonthlyMaintenanceCostExcelExport;
use App\Filament\Widgets\Costs\BudgetVsSpentWidget;
use App\Filament\Widgets\Costs\CostBreakdownWidget;
use App\Filament\Widgets\Costs\MonthlyCostByTypeWidget;
use App\Filament\Widgets\Costs\MonthlyWorkOrderCostsWidget;
use App\Models\Plant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Control de gastos de mantenimiento por mes.
 *
 * Cuánto se gastó, en qué (mano de obra / repuestos / terceros), por tipo, y
 * contra el presupuesto que la gerencia le asignó al área ese mes. El
 * presupuesto se fija en el recurso «Presupuestos»; acá solo se lee y se
 * compara.
 */
class GastosDeMantenimiento extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/gastos-mantenimiento';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Gastos de Mantenimiento';

    protected static ?string $title = 'Gastos de Mantenimiento';

    protected static ?int $navigationSort = 4;

    // Consolidado dentro del Dashboard. La ruta sigue viva; se saca del menú.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            BudgetVsSpentWidget::class,
            CostBreakdownWidget::class,
            MonthlyCostByTypeWidget::class,
            MonthlyWorkOrderCostsWidget::class,
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Exportar a Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (): BinaryFileResponse|StreamedResponse {
                    $filters = $this->filters ?? [];
                    $plantId = $filters['plant_id'] ?? Plant::where('tenant_id', Filament::getTenant()->id)
                        ->orderBy('name')
                        ->value('id');

                    return app(MonthlyMaintenanceCostExcelExport::class)->download(
                        Filament::getTenant()->id,
                        $plantId,
                        (int) ($filters['year'] ?? now()->year),
                        (int) ($filters['month'] ?? now()->month),
                    );
                }),
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('plant_id')
                    ->label('Planta')
                    ->options(fn (): array => Plant::where('tenant_id', Filament::getTenant()->id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->default(fn (): ?string => Plant::where('tenant_id', Filament::getTenant()->id)
                        ->orderBy('name')
                        ->value('id'))
                    ->live()
                    ->selectablePlaceholder(false),
                Select::make('year')
                    ->label('Año')
                    ->options(collect(range((int) now()->year, (int) now()->year - 3))
                        ->mapWithKeys(fn (int $y): array => [$y => (string) $y])
                        ->all())
                    ->default((int) now()->year)
                    ->live()
                    ->selectablePlaceholder(false),
                Select::make('month')
                    ->label('Mes')
                    ->options([
                        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                    ])
                    ->default((int) now()->month)
                    ->live()
                    ->selectablePlaceholder(false),
            ]);
    }
}
