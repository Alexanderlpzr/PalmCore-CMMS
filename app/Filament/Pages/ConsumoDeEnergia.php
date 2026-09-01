<?php

namespace App\Filament\Pages;

use App\Domain\Reports\Services\EnergiaPdfService;
use App\Filament\Concerns\DescargaInformePdf;
use App\Filament\Concerns\HasPeriodFilterForm;
use App\Filament\Widgets\Executive\PlantEnergyStatsWidget;
use App\Filament\Widgets\Executive\PlantEnergyYearTableWidget;
use App\Models\EnergyMeter;
use App\Models\Plant;
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
 * El informe de energía que la planta reporta cada mes.
 *
 *     KWh TOTAL      = red + planta eléctrica + turbina
 *     KWh/RFF        = KWh TOTAL / fruta procesada
 *     ENERGÍA LIMPIA = turbina / KWh TOTAL
 *
 * Comparte denominador con la productividad —la fruta del mes— y por eso el dato no se
 * captura dos veces: sale del calendario de producción.
 *
 * Esta página lee; no escribe. La captura vive en «Energía», donde el operario anota lo
 * que marca cada contador.
 */
class ConsumoDeEnergia extends BaseDashboard
{
    use DescargaInformePdf;
    use HasFiltersForm;
    use HasPeriodFilterForm;

    protected static string $routePath = '/consumo-de-energia';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Consumo de Energía';

    protected static ?string $title = 'Consumo de Energía de Planta';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', EnergyMeter::class) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

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
            PlantEnergyStatsWidget::class,
            PlantEnergyYearTableWidget::class,
        ];
    }

    /**
     * El atajo a la captura, desde el indicador que la consume. La página sigue sin
     * escribir: solo lleva al sitio donde sí se escribe.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->descargarInformeAction(EnergiaPdfService::class, 'El informe de consumo de energía del período elegido'),
            Action::make('anotarLecturas')
                ->label('Anotar lecturas')
                ->icon(Heroicon::OutlinedBolt)
                ->url(fn (): string => Energia::getUrl())
                ->visible(fn (): bool => auth()->user()?->can('create', EnergyMeter::class) ?? false),
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

            ...$this->periodFilterComponents(defaultPreset: 'month'),
        ]);
    }
}
