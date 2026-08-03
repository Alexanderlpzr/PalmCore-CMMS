<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Analytics\Support\DashboardPeriod;
use App\Models\Plant;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Las horas con las que se calculan los tres indicadores, con el nombre que
 * tienen en la planilla de la planta.
 *
 *     HP    horas pagadas          HPREN horas de prensado
 *     HASEO horas de aseo          HMTTO horas de paro por mantenimiento
 *     FP    fruta procesada        HOPER otras paradas
 *
 * Existe para que el número se pueda auditar en la reunión sin salir de la
 * pantalla: HASEO + HMTTO + HOPER cierra contra las horas perdidas, y éstas
 * contra HP − HPREN. Si la cuenta no cuadra, el problema está en los paros
 * registrados, y este widget es donde se ve.
 */
class PlantHoursBreakdownWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $plant = $this->selectedPlant();

        if ($plant === null) {
            return [Stat::make('Desglose de horas', 'Sin plantas registradas')];
        }

        [$from, $to] = DashboardPeriod::snapshotWindow($this->pageFilters);

        $metrics = app(PlantKpiService::class)->calculate($plant, $from, $to);

        $hours = fn (float $value): string => number_format($value, 1, ',', '.').' h';
        $unplannedMaintenance = round($metrics['maintenance_lost_hours'] - $metrics['cleaning_hours'], 2);
        $pressable = round($metrics['programmed_hours'] - $metrics['cleaning_hours'], 2);

        return [
            Stat::make('HP · Horas pagadas', $hours($metrics['programmed_hours']))
                ->description('Del calendario de producción')
                ->color('gray'),

            Stat::make('HASEO · Aseo y preventivo', $hours($metrics['cleaning_hours']))
                ->description('Paros de mantenimiento programados')
                ->color('info'),

            Stat::make('Horas prensables · HP − HASEO', $hours($pressable))
                ->description('Denominador de eficiencia y productividad')
                ->color('primary'),

            Stat::make('HPREN · Horas de prensado', $hours($metrics['effective_hours']))
                ->description('HP menos todas las horas perdidas')
                ->color('success'),

            Stat::make('HMTTO · Paro por mantenimiento', $hours($unplannedMaintenance))
                ->description('Correctivo, no programado')
                ->color($unplannedMaintenance > 0 ? 'danger' : 'gray'),

            Stat::make('HOPER · Otras paradas', $hours($metrics['other_lost_hours']))
                ->description('Operativas, falta de RFF, atascamiento, externos')
                ->color($metrics['other_lost_hours'] > 0 ? 'warning' : 'gray'),

            Stat::make('FP · Fruta procesada', number_format($metrics['processed_tons'], 1, ',', '.').' t')
                ->description('Del calendario de producción')
                ->color('primary'),

            Stat::make('Horas perdidas', $hours($metrics['lost_hours']))
                ->description('HASEO + HMTTO + HOPER, sin contar dos veces los paros solapados')
                ->color('gray'),

            // El cuadre. Si falla, no es un error de cálculo: es que hay paros
            // fuera de las horas programadas, y eso hay que verlo, no esconderlo.
            Stat::make('Cuadre · HPREN + perdidas', $hours(round($metrics['effective_hours'] + $metrics['lost_hours'], 2)))
                ->description(abs($metrics['effective_hours'] + $metrics['lost_hours'] - $metrics['programmed_hours']) < 0.01
                    ? 'Cuadra contra HP'
                    : 'No cuadra contra HP: hay paros fuera de las horas pagadas')
                ->color(abs($metrics['effective_hours'] + $metrics['lost_hours'] - $metrics['programmed_hours']) < 0.01
                    ? 'success'
                    : 'warning'),
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
