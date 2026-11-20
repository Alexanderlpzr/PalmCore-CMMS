<?php

namespace App\Filament\Pages;

use App\Domain\Reports\Services\DashboardPdfService;
use App\Filament\Concerns\DescargaInformePdf;
use App\Filament\Concerns\HasPeriodFilterForm;
use App\Filament\Concerns\RequiresMaintenanceOrProductionAccess;
use App\Filament\Widgets\Analytics\CostByEquipmentWidget;
use App\Filament\Widgets\Analytics\DowntimeByEquipmentWidget;
use App\Filament\Widgets\Analytics\DowntimeByReasonWidget;
use App\Filament\Widgets\Analytics\DowntimeBySectionWidget;
use App\Filament\Widgets\Analytics\DowntimeByStoppageCategoryWidget;
use App\Filament\Widgets\Analytics\DowntimeMaintenanceBreakdownWidget;
use App\Filament\Widgets\Analytics\ParetoFailuresWidget;
use App\Filament\Widgets\Costs\MonthlyCostByTypeWidget;
use App\Filament\Widgets\Executive\DashboardSummaryWidget;
use App\Filament\Widgets\Executive\MonthlyProductionWidget;
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
use Filament\Widgets\WidgetConfiguration;
use Livewire\Attributes\Url;
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
    use RequiresMaintenanceOrProductionAccess;

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

    public const DEFAULT_SECTION = 'produccion';

    /** Las pestañas del Dashboard, en orden. */
    public const SECTIONS = [
        'produccion' => 'Producción',
        'paros' => 'Paros',
        'confiabilidad' => 'Confiabilidad',
        'costos' => 'Costos',
    ];

    /**
     * La pestaña que se mira. Va en la dirección (`?ver=paros`) para que un enlace o
     * recargar la página la conserve.
     */
    #[Url(as: 'ver')]
    public string $seccion = self::DEFAULT_SECTION;

    /**
     * Arriba, el resumen —cinco cifras y las pestañas—; debajo, solo las gráficas de la
     * pestaña elegida. Antes eran once bloques seguidos, con tablas, y había que bajar
     * mucho para ver los costos (pedido 2026-11-19).
     *
     * @return array<class-string|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            DashboardSummaryWidget::class,
            ...match ($this->section()) {
                // La producción de cada mes contra el año anterior, y los tres indicadores de planta.
                'produccion' => [
                    MonthlyProductionWidget::class,
                    PlantEfficiencyStatsWidget::make(['heroMetric' => 'availability']),
                ],
                'paros' => [
                    DowntimeMaintenanceBreakdownWidget::class,
                    DowntimeByReasonWidget::class,
                    DowntimeBySectionWidget::class,
                    DowntimeByStoppageCategoryWidget::class,
                    DowntimeByEquipmentWidget::class,
                ],
                'confiabilidad' => [
                    MaintenanceComplianceWidget::class,
                    ParetoFailuresWidget::class,
                ],
                'costos' => [
                    CostByEquipmentWidget::class,
                    MonthlyCostByTypeWidget::class,
                ],
            },
        ];
    }

    /** @return array<string, mixed> */
    public function getWidgetData(): array
    {
        return ['seccion' => $this->section()];
    }

    /** Una pestaña que no existe —un enlace viejo o tecleado— abre la primera. */
    private function section(): string
    {
        return array_key_exists($this->seccion, self::SECTIONS) ? $this->seccion : self::DEFAULT_SECTION;
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
