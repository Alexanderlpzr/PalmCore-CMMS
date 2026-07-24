<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\Support\DashboardPeriod;
use App\Filament\Widgets\Analytics\DowntimeByEquipmentWidget;
use App\Filament\Widgets\Analytics\DowntimeByReasonWidget;
use App\Filament\Widgets\Analytics\DowntimeByReportedTypeWidget;
use App\Filament\Widgets\Analytics\DowntimeBySectionWidget;
use App\Filament\Widgets\Analytics\DowntimeByStoppageCategoryWidget;
use App\Filament\Widgets\Executive\PlantEfficiencyStatsWidget;
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
 * «Indicadores de Paros» — la hoja de indicadores del Excel de la extractora en
 * una sola pantalla, filtrable por mes/año: eficiencia de planta y MTBF, horas por
 * Tipo I, por Sección, por Tipo II y por equipo. Todo sale del registro manual de
 * paros y del calendario de producción; esta página es la lectura, no la captura.
 */
class IndicadoresDeParos extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/indicadores-de-paros';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Indicadores de Paros';

    protected static ?string $title = 'Indicadores de Paros';

    protected static ?int $navigationSort = 2;

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            PlantEfficiencyStatsWidget::class,
            DowntimeByReportedTypeWidget::class,
            DowntimeByStoppageCategoryWidget::class,
            DowntimeBySectionWidget::class,
            DowntimeByReasonWidget::class,
            DowntimeByEquipmentWidget::class,
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
