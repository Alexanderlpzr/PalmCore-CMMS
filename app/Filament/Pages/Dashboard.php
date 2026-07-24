<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\Support\DashboardPeriod;
use App\Filament\Widgets\Analytics\CostByEquipmentWidget;
use App\Filament\Widgets\Analytics\DowntimeByEquipmentWidget;
use App\Filament\Widgets\Analytics\DowntimeByReasonWidget;
use App\Filament\Widgets\Analytics\DowntimeByReportedTypeWidget;
use App\Filament\Widgets\Analytics\DowntimeBySectionWidget;
use App\Filament\Widgets\Analytics\DowntimeByStoppageCategoryWidget;
use App\Filament\Widgets\Analytics\ParetoFailuresWidget;
use App\Filament\Widgets\Costs\MonthlyCostByTypeWidget;
use App\Filament\Widgets\Executive\PlantEfficiencyStatsWidget;
use App\Filament\Widgets\Reliability\MaintenanceComplianceWidget;
use App\Models\Plant;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * El tablero único de la planta: eficiencia, paros, confiabilidad y costos en una
 * sola pantalla, con un filtro de planta + mes/año arriba que manda sobre todo.
 *
 * Antes esto vivía repartido en cuatro pantallas que se pisaban (Eficiencia de
 * Planta, Indicadores de Paros, Resumen Ejecutivo, Gastos). Se consolidó aquí y
 * aquéllas se sacaron del menú. Las gráficas se mantienen chicas y concisas: una
 * foto del mes, como la hoja de indicadores que maneja la extractora.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = 1;

    /**
     * Grilla de 3 columnas para que las gráficas queden pequeñas y ordenadas.
     */
    public function getColumns(): array|int
    {
        return ['default' => 1, 'md' => 2, 'xl' => 3];
    }

    /**
     * Selección curada, agrupada por bloque (el orden final lo fija el $sort de
     * cada widget): Resumen → Paros → Confiabilidad → Costos.
     *
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            // Resumen
            PlantEfficiencyStatsWidget::class,
            // Paros
            DowntimeByReportedTypeWidget::class,
            DowntimeByReasonWidget::class,
            DowntimeBySectionWidget::class,
            DowntimeByStoppageCategoryWidget::class,
            DowntimeByEquipmentWidget::class,
            // Confiabilidad
            MaintenanceComplianceWidget::class,
            ParetoFailuresWidget::class,
            // Costos
            CostByEquipmentWidget::class,
            MonthlyCostByTypeWidget::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
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

            Select::make('preset')
                ->label('Periodo')
                ->options([
                    'month' => 'Un mes',
                    'year' => 'Año completo',
                    DashboardPeriod::DEFAULT_PRESET => 'Últimos 12 meses',
                ])
                ->default('month')
                ->live()
                ->selectablePlaceholder(false),

            Select::make('year')
                ->label('Año')
                ->options(DashboardPeriod::yearOptions())
                ->default(now()->year)
                ->visible(fn (Get $get): bool => in_array($get('preset'), ['year', 'month'], strict: true)),

            Select::make('month')
                ->label('Mes')
                ->options(DashboardPeriod::monthOptions())
                ->default(now()->month)
                ->visible(fn (Get $get): bool => $get('preset') === 'month'),
        ]);
    }
}
