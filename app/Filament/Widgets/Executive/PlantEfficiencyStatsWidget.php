<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Analytics\Support\DashboardPeriod;
use App\Filament\Resources\ProductionCalendar\ProductionCalendarResource;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * El resultado: los tres indicadores de planta del período elegido, con la misma
 * cuenta de PlantKpiService::calculate() que usa la API — el widget la lee, no la
 * duplica.
 *
 *     Eficiencia     = HPREN / (HP − HASEO)
 *     Productividad  = FP    / (HP − HASEO)
 *     Disponibilidad = (HP − HASEO − HMTTO) / HP
 *
 * El desglose de horas que hay detrás vive en la página «Productividad y
 * Eficiencia»: aquí sólo va el número que se mira de reojo, con el período al
 * lado para que nadie lea el de un mes creyendo que es el del año.
 *
 * Productividad muestra «Sin fruta registrada» y no cero cuando no hay toneladas
 * en el calendario: cero toneladas por hora es un mes catastrófico, y un mes sin
 * capturar no es lo mismo que un mes sin producir.
 */
class PlantEfficiencyStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $plant = $this->selectedPlant();

        if ($plant === null) {
            return [Stat::make('Eficiencia de planta', 'Sin plantas registradas')];
        }

        // El período elegido manda, incluido «últimos 12 meses»: antes ese preset
        // caía al mes en curso sin decirlo, y el número en pantalla no era el que
        // el filtro prometía.
        [$from, $to] = DashboardPeriod::snapshotWindow($this->pageFilters);

        $metrics = app(PlantKpiService::class)->calculate($plant, $from, $to);
        $period = DashboardPeriod::label($this->pageFilters);

        $efficiency = $metrics['efficiency_percentage'];
        $productivity = $metrics['productivity_tons_per_hour'];
        $availability = $metrics['availability_percentage'];
        $pressable = round($metrics['programmed_hours'] - $metrics['cleaning_hours'], 1);

        // Una tarjeta que dice «falta el dato» y no lleva a cargarlo obliga a salir a
        // buscar el menú donde se captura. Solo se enlaza cuando falta: con el número
        // puesto, el enlace sería ruido.
        $captureUrl = (auth()->user()?->can('create', ProductionCalendarDay::class) ?? false)
            ? ProductionCalendarResource::getUrl('diaria')
            : null;

        $percentColor = fn (?float $value): string => match (true) {
            $value === null => 'gray',
            $value >= 90 => 'success',
            $value >= 80 => 'warning',
            default => 'danger',
        };

        return [
            Stat::make('Eficiencia', $efficiency !== null ? $efficiency.'%' : 'Sin horas pagadas')
                ->description($efficiency !== null
                    ? number_format($metrics['effective_hours'], 1).' h prensadas de '.number_format($pressable, 1).' h prensables · '.$period
                    : 'Falta el calendario de producción — cárgalo aquí')
                ->url($efficiency === null ? $captureUrl : null)
                ->color($percentColor($efficiency)),

            Stat::make('Productividad', $productivity !== null ? number_format($productivity, 2).' t/h' : 'Sin fruta registrada')
                ->description($productivity !== null
                    ? number_format($metrics['processed_tons'], 0).' t sobre '.number_format($pressable, 1).' h prensables · '.$period
                    : 'Captura las toneladas de la semana — hazlo aquí')
                ->url($productivity === null ? $captureUrl : null)
                ->color($productivity !== null ? 'primary' : 'gray'),

            Stat::make('Disponibilidad', $availability !== null ? $availability.'%' : 'Sin horas pagadas')
                ->description(number_format($metrics['maintenance_lost_hours'], 1).' h que mantenimiento le quitó a la planta · '.$period)
                ->color($percentColor($availability)),

            Stat::make('MTBF / MTTR Planta', ($metrics['mtbf_hours'] !== null ? number_format($metrics['mtbf_hours'], 1) : '—').
                ' / '.($metrics['mttr_hours'] !== null ? number_format($metrics['mttr_hours'], 1) : '—').' h')
                ->description($metrics['failure_count'].' falla(s) de mantenimiento · '.$period),
        ];
    }

    private function selectedPlant(): ?Plant
    {
        $plantId = $this->pageFilters['plant_id'] ?? null;

        if ($plantId !== null) {
            return Plant::find($plantId);
        }

        return Plant::orderBy('name')->first();
    }
}
