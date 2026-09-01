<?php

namespace App\Filament\Pages;

use App\Domain\Reports\Services\DashboardPdfService;
use App\Filament\Concerns\DescargaInformePdf;
use App\Filament\Concerns\HasPeriodFilterForm;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * El tablero único de la planta: eficiencia, paros, confiabilidad y costos en una
 * sola pantalla, con un filtro de planta + período arriba que manda sobre todo.
 *
 * Antes esto vivía repartido en cuatro pantallas que se pisaban (Eficiencia de
 * Planta, Indicadores de Paros, Resumen Ejecutivo, Gastos). Se consolidó aquí y
 * aquéllas se sacaron del menú. Las gráficas se mantienen chicas y concisas: una
 * foto del mes, como la hoja de indicadores que maneja la extractora.
 *
 * Aquí va el resultado, no la auditoría: los tres indicadores de planta se leen
 * de reojo y quien necesite ver las horas que hay detrás las tiene en
 * «Productividad y Eficiencia», que sí volvió al menú.
 */
class Dashboard extends BaseDashboard
{
    use DescargaInformePdf;
    use HasFiltersForm;
    use HasPeriodFilterForm;

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

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->descargarInformeAction(DashboardPdfService::class, 'El informe de paros, confiabilidad y costos'),
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

            // Sale del trait compartido para que el rango de meses —que
            // DashboardPeriod siempre supo resolver— también esté aquí: antes
            // este filtro sólo ofrecía mes, año y últimos 12, y el rango era una
            // opción que existía en el código y en ninguna pantalla.
            ...$this->periodFilterComponents(defaultPreset: 'month'),
        ]);
    }
}
