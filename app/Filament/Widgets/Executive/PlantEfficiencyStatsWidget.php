<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\PlantKpiService;
use App\Models\Plant;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Eficiencia = horas efectivas / horas programadas, del mes en curso — la
 * misma cuenta de PlantKpiService::calculate() que ya usaba la API, ahora
 * leída directo por el widget en vez de duplicar la fórmula.
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

        $metrics = app(PlantKpiService::class)->calculate(
            $plant,
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        );

        $efficiency = $metrics['efficiency_percentage'];

        return [
            Stat::make('Eficiencia', $efficiency !== null ? $efficiency.'%' : 'Sin horas programadas')
                ->color(match (true) {
                    $efficiency === null => 'gray',
                    $efficiency >= 90 => 'success',
                    $efficiency >= 80 => 'warning',
                    default => 'danger',
                }),

            Stat::make('Horas Efectivas', number_format($metrics['effective_hours'], 1).' h')
                ->description('de '.number_format($metrics['programmed_hours'], 1).' h programadas'),

            Stat::make('Horas Perdidas', number_format($metrics['lost_hours'], 1).' h')
                ->description(number_format($metrics['maintenance_lost_hours'], 1).' h de mantenimiento'),

            Stat::make('MTBF / MTTR Planta', ($metrics['mtbf_hours'] !== null ? number_format($metrics['mtbf_hours'], 1) : '—').
                ' / '.($metrics['mttr_hours'] !== null ? number_format($metrics['mttr_hours'], 1) : '—').' h')
                ->description($metrics['failure_count'].' falla(s) de mantenimiento'),
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
