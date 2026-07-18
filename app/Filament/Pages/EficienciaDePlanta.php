<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Executive\PlantEfficiencyStatsWidget;
use App\Filament\Widgets\Executive\PlantMonthlyEfficiencyHistoryWidget;
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
 * Eficiencia = horas efectivas / horas programadas. El calendario de
 * producción que alimenta el denominador ya se administra en el recurso
 * "Calendario de producción" (con su acción "Programar mes") — esta página
 * es la lectura, no la captura.
 */
class EficienciaDePlanta extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/eficiencia-de-planta';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBoltSlash;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Eficiencia de Planta';

    protected static ?string $title = 'Eficiencia de Planta';

    protected static ?int $navigationSort = 3;

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            PlantEfficiencyStatsWidget::class,
            PlantMonthlyEfficiencyHistoryWidget::class,
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
            ]);
    }
}
