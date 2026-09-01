<?php

namespace App\Filament\Pages;

use App\Domain\Reports\Services\ProductividadPdfService;
use App\Filament\Concerns\DescargaInformePdf;
use App\Filament\Concerns\HasPeriodFilterForm;
use App\Filament\Resources\ProductionCalendar\ProductionCalendarResource;
use App\Filament\Widgets\Executive\PlantEfficiencyStatsWidget;
use App\Filament\Widgets\Executive\PlantHoursBreakdownWidget;
use App\Filament\Widgets\Executive\PlantMonthlyEfficiencyHistoryWidget;
use App\Filament\Widgets\Executive\PlantMonthlyProductivityHistoryWidget;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Los tres indicadores mensuales de planta, con las horas que hay detrás.
 *
 *     Eficiencia     = HPREN / (HP − HASEO)
 *     Productividad  = FP    / (HP − HASEO)
 *     Disponibilidad = (HP − HASEO − HMTTO) / HP
 *
 * Una sola página para los tres, y no una por indicador: comparten denominador y
 * salen de las mismas horas, así que separarlos obligaría a repetir el desglose
 * en dos pantallas para cambiar un número. La planta ya los lee juntos en una
 * hoja.
 *
 * El Dashboard muestra el resultado; aquí está la auditoría — el desglose de
 * horas con el nombre de la planilla y el histórico de meses cerrados. La
 * captura sigue en el recurso «Calendario de producción»: esta página lee, no
 * escribe.
 */
class ProductividadYEficiencia extends BaseDashboard
{
    use DescargaInformePdf;
    use HasFiltersForm;
    use HasPeriodFilterForm;

    protected static string $routePath = '/productividad-y-eficiencia';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Productividad y Eficiencia';

    protected static ?string $title = 'Productividad y Eficiencia de Planta';

    protected static ?int $navigationSort = 2;

    public function getColumns(): array|int
    {
        return 1;
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            PlantEfficiencyStatsWidget::class,
            PlantHoursBreakdownWidget::class,
            PlantMonthlyEfficiencyHistoryWidget::class,
            PlantMonthlyProductivityHistoryWidget::class,
        ];
    }

    /**
     * El atajo a la captura, desde el indicador que la necesita.
     *
     * Esta página sigue sin escribir —lee y audita—, pero quien descubre aquí que le
     * falta la fruta de la semana no tiene por qué salir a buscar en qué menú se
     * carga. El enlace no rompe la separación: la lleva al sitio donde sí se escribe.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->descargarInformeAction(ProductividadPdfService::class, 'El informe de productividad y eficiencia del período elegido'),
            Action::make('cargarProduccion')
                ->label('Cargar producción')
                ->icon(Heroicon::OutlinedTableCells)
                ->url(fn (): string => ProductionCalendarResource::getUrl('diaria'))
                ->visible(fn (): bool => auth()->user()?->can('create', ProductionCalendarDay::class) ?? false),
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

            // Abre en el mes en curso: es el período en el que la planta piensa,
            // y el que el ingeniero mira al entrar.
            ...$this->periodFilterComponents(defaultPreset: 'month'),
        ]);
    }
}
