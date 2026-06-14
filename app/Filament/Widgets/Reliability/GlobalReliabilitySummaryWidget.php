<?php

namespace App\Filament\Widgets\Reliability;

use App\Models\EquipmentKpi;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GlobalReliabilitySummaryWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $summary = EquipmentKpi::query()
            ->selectRaw(
                'COUNT(DISTINCT equipment_id) AS total_equipment,
                 COUNT(DISTINCT CASE WHEN failure_count > 0 THEN equipment_id END) AS equipments_with_failures,
                 SUM(failure_count) AS total_failures,
                 AVG(availability_percentage) AS avg_availability,
                 AVG(mtbf_hours) AS avg_mtbf,
                 AVG(mttr_hours) AS avg_mttr,
                 SUM(downtime_hours) AS total_downtime'
            )
            ->first();

        $totalEquipment = (int) ($summary->total_equipment ?? 0);
        $totalFailures = (int) ($summary->total_failures ?? 0);
        $avgAvailability = round((float) ($summary->avg_availability ?? 0), 2);
        $avgMtbf = $summary->avg_mtbf !== null ? round((float) $summary->avg_mtbf, 2) : null;
        $avgMttr = $summary->avg_mttr !== null ? round((float) $summary->avg_mttr, 2) : null;
        $totalDowntime = round((float) ($summary->total_downtime ?? 0), 2);

        return [
            Stat::make('Equipos Monitoreados', number_format($totalEquipment)),

            Stat::make('Disponibilidad Promedio', $avgAvailability.'%')
                ->color($avgAvailability >= 95 ? 'success' : ($avgAvailability >= 85 ? 'warning' : 'danger')),

            Stat::make('MTBF Promedio', $avgMtbf !== null ? number_format($avgMtbf, 2).' h' : 'Sin fallas registradas'),

            Stat::make('MTTR Promedio', $avgMttr !== null ? number_format($avgMttr, 2).' h' : 'Sin fallas registradas'),

            Stat::make('Total de Fallas', number_format($totalFailures)),

            Stat::make('Horas Totales de Parada', number_format($totalDowntime, 2).' h'),
        ];
    }
}
