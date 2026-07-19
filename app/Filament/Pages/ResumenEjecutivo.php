<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPeriodFilterForm;
use App\Filament\Widgets\Executive\AreaHealthWidget;
use App\Filament\Widgets\Executive\AvailabilityTrendWidget;
use App\Filament\Widgets\Executive\CostByTypeWidget;
use App\Filament\Widgets\Executive\CostTrendWidget;
use App\Filament\Widgets\Executive\ExecutiveSummaryWidget;
use App\Filament\Widgets\Executive\TopCriticalEquipmentWidget;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * El resumen que la gerencia mira una vez al mes: disponibilidad, MTBF, MTTR,
 * costos y qué áreas/equipos concentran el problema. Extiende el Dashboard
 * base de Filament (no Page) por la misma razón que App\Filament\Pages\Dashboard
 * lo hace: es la clase que ya sabe renderizar una lista de widgets sin tener
 * que escribir una vista Blade a mano.
 *
 * El selector de período solo mueve las cifras de costo (Costo Mensual,
 * Costos por Tipo, Tendencia de Costo, la columna de costo en Salud por Área
 * y Equipos Críticos) — Disponibilidad/MTBF/MTTR vienen de equipment_kpis,
 * una foto del estado actual (ventana móvil), no un historial mes a mes, así
 * que no responden al filtro.
 */
class ResumenEjecutivo extends BaseDashboard
{
    use HasFiltersForm;
    use HasPeriodFilterForm;

    protected static string $routePath = '/resumen-ejecutivo';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Resumen Ejecutivo';

    protected static ?string $title = 'Resumen Ejecutivo';

    protected static ?int $navigationSort = 2;

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            ExecutiveSummaryWidget::class,
            AreaHealthWidget::class,
            TopCriticalEquipmentWidget::class,
            CostByTypeWidget::class,
            AvailabilityTrendWidget::class,
            CostTrendWidget::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $this->periodFilterForm($schema);
    }
}
