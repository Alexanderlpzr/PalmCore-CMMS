<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Assets\Enums\StoppageCategory;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\ProductionCalendarDay;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * KPIs de PLANTA — not of equipment.
 *
 * The plant reports one number every month:
 *
 *     Eficiencia = horas efectivas / horas programadas
 *     horas efectivas = horas programadas − horas perdidas por paros
 *
 * Junio 2026: 413,4 h efectivas sobre 452 h programadas = 91,46 %.
 *
 * MTBF and MTTR are computed here on the same basis — over the plant's *effective*
 * hours and over the failures maintenance actually owns, so a month lost to «falta
 * de fruta» does not read as a month of unreliable machines.
 */
class PlantKpiService
{
    /**
     * @return array{
     *     programmed_hours: float,
     *     lost_hours: float,
     *     effective_hours: float,
     *     maintenance_lost_hours: float,
     *     efficiency_percentage: ?float,
     *     failure_count: int,
     *     mtbf_hours: ?float,
     *     mttr_hours: ?float,
     * }
     */
    public function calculate(Plant $plant, CarbonInterface $from, CarbonInterface $to): array
    {
        $programmed = $this->programmedHours($plant, $from, $to);
        $lost = $this->lostHours($plant, $from, $to);
        $maintenanceLost = $this->lostHours($plant, $from, $to, maintenanceOnly: true);
        // MTTR is time-to-*repair*: a programmed intervention is maintenance time,
        // but it is not a repair, and counting it would flatter the number.
        $repairHours = $this->lostHours($plant, $from, $to, maintenanceOnly: true, includePlanned: false);

        // A plant cannot lose more hours than it was scheduled to run: an overlap
        // in the paro log must not produce negative effective hours.
        $effective = max(0.0, round($programmed - $lost, 2));

        $failures = $this->failureCount($plant, $from, $to);

        return [
            'programmed_hours' => $programmed,
            'lost_hours' => $lost,
            'effective_hours' => $effective,
            'maintenance_lost_hours' => $maintenanceLost,
            'efficiency_percentage' => $programmed > 0
                ? round($effective / $programmed * 100, 2)
                : null,
            'failure_count' => $failures,
            'mtbf_hours' => $failures > 0 ? round($effective / $failures, 2) : null,
            'mttr_hours' => $failures > 0 ? round($repairHours / $failures, 2) : null,
        ];
    }

    /** The denominator: what the planner said the plant would run. */
    public function programmedHours(Plant $plant, CarbonInterface $from, CarbonInterface $to): float
    {
        return round((float) ProductionCalendarDay::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->whereBetween('calendar_date', [$from->toDateString(), $to->toDateString()])
            ->sum('programmed_hours'), 2);
    }

    /**
     * Hours the plant did not run because something stopped it. Only stoppages
     * flagged as production-affecting count — a failure recorded while the line
     * kept running cost no production hours.
     */
    public function lostHours(
        Plant $plant,
        CarbonInterface $from,
        CarbonInterface $to,
        bool $maintenanceOnly = false,
        bool $includePlanned = true,
    ): float {
        $query = EquipmentDowntimeEvent::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->where('affects_production', true)
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$from, $to]);

        if ($maintenanceOnly) {
            $query->whereIn('stoppage_category', $this->maintenanceCategories($includePlanned));
        }

        $minutes = (float) $query->selectRaw(
            'COALESCE(SUM(COALESCE(duration_minutes,
                EXTRACT(EPOCH FROM (ended_at - started_at)) / 60)), 0) AS minutes'
        )->value('minutes');

        return round($minutes / 60, 2);
    }

    /**
     * Failures for plant MTBF: unplanned stoppages maintenance is accountable for.
     * A programmed intervention is not a failure, and neither is a lack of fruit.
     */
    public function failureCount(Plant $plant, CarbonInterface $from, CarbonInterface $to): int
    {
        return EquipmentDowntimeEvent::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->where('was_planned', false)
            ->whereIn('stoppage_category', $this->maintenanceCategories(includePlanned: false))
            ->whereBetween('started_at', [$from, $to])
            ->count();
    }

    /**
     * Freeze a month. Re-running it recalculates the same row instead of adding a
     * second one, so a late-entered paro corrects the month rather than duplicating it.
     */
    public function snapshotMonth(Plant $plant, int $year, int $month): PlantMonthlyKpi
    {
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $metrics = $this->calculate($plant, $from, $to);

        return PlantMonthlyKpi::withoutGlobalScopes()->updateOrCreate(
            [
                'plant_id' => $plant->id,
                'year' => $year,
                'month' => $month,
            ],
            [
                'tenant_id' => $plant->tenant_id,
                'programmed_hours' => $metrics['programmed_hours'],
                'lost_hours' => $metrics['lost_hours'],
                'effective_hours' => $metrics['effective_hours'],
                'maintenance_lost_hours' => $metrics['maintenance_lost_hours'],
                'failure_count' => $metrics['failure_count'],
                'mtbf_hours' => $metrics['mtbf_hours'],
                'mttr_hours' => $metrics['mttr_hours'],
                'calculated_at' => now(),
            ],
        )->refresh();
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * @return list<string>
     */
    private function maintenanceCategories(bool $includePlanned = true): array
    {
        return array_values(array_map(
            fn (StoppageCategory $c): string => $c->value,
            array_filter(
                StoppageCategory::cases(),
                fn (StoppageCategory $c): bool => $c->isMaintenanceResponsibility()
                    && ($includePlanned || ! $c->isPlanned()),
            ),
        ));
    }
}
