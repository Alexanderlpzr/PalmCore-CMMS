<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Services\LostHoursCalculator;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\ProductionCalendarDay;
use App\Models\WorkOrderTimeLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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
    public function __construct(private readonly LostHoursCalculator $lostHours) {}

    /**
     * Two different repair numbers, on purpose — see {@see laborBreakdown()}.
     *
     * @return array{
     *     programmed_hours: float,
     *     lost_hours: float,
     *     effective_hours: float,
     *     maintenance_lost_hours: float,
     *     efficiency_percentage: ?float,
     *     failure_count: int,
     *     mtbf_hours: ?float,
     *     mttr_hours: ?float,
     *     wrench_hours: float,
     *     waiting_hours: float,
     *     mttr_wrench_hours: ?float,
     *     classified_failure_count: int,
     * }
     */
    public function calculate(Plant $plant, CarbonInterface $from, CarbonInterface $to): array
    {
        $programmed = $this->programmedHours($plant, $from, $to);
        $lost = $this->lostHours($plant, $from, $to);
        $maintenanceLost = $this->lostHours($plant, $from, $to, maintenanceOnly: true);
        // Horas de paro por falla: a programmed intervention is maintenance time,
        // but it is not a failure, and counting it would flatter the number. It is
        // also the one figure measured per failure and not on the plant's clock —
        // two machines broken at once are two repairs, not one.
        $downtimeHours = $this->lostHours->sumHours(
            $this->eventsFor($plant)->where('was_planned', false)->maintenanceOwned(),
            $from,
            $to,
        );

        // A paro can still fall outside the programmed hours (the plant was not
        // scheduled to run), so effective hours are floored at zero.
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
            // Horas de paro por falla: lo que le costó a producción. Incluye la
            // espera del repuesto, porque la máquina estuvo abajo igual.
            'mttr_hours' => $failures > 0 ? round($downtimeHours / $failures, 2) : null,
            ...$this->laborBreakdown($plant, $from, $to),
        ];
    }

    /**
     * Wrench time vs waiting, and the MTTR that only counts the wrench.
     *
     * The gap between this and `mttr_hours` is the whole point: «reparamos en 2 h
     * pero la máquina estuvo 9 h abajo» is the sentence that justifies a critical
     * spares stock. Reporting only the wrench number would make the indicator
     * improve without the plant improving at all.
     *
     * The denominator is deliberately *not* `failure_count`: it is the failures that
     * actually have classified time logs. A paro typed up by the supervisor with no
     * OT behind it has no wrench time to measure, and averaging over it would invent
     * one. With nothing classified the answer is `null`, not zero.
     *
     * @return array{wrench_hours: float, waiting_hours: float, mttr_wrench_hours: ?float, classified_failure_count: int}
     */
    private function laborBreakdown(Plant $plant, CarbonInterface $from, CarbonInterface $to): array
    {
        $workOrderIds = $this->eventsFor($plant)
            ->where('was_planned', false)
            ->maintenanceOwned()
            ->whereNotNull('work_order_id')
            ->whereBetween('started_at', [$from, $to])
            ->pluck('work_order_id');

        $logs = WorkOrderTimeLog::withoutGlobalScopes()
            ->whereIn('work_order_id', $workOrderIds)
            ->whereNotNull('activity_type')
            ->get();

        $hoursOf = fn (bool $wrenchTime): float => round(
            $logs->filter(fn (WorkOrderTimeLog $log): bool => $log->isWrenchTime() === $wrenchTime)
                ->sum(fn (WorkOrderTimeLog $log): float => $log->computedHours()),
            2,
        );

        $wrench = $hoursOf(true);
        $classified = $logs->pluck('work_order_id')->unique()->count();

        return [
            'wrench_hours' => $wrench,
            'waiting_hours' => $hoursOf(false),
            'mttr_wrench_hours' => $classified > 0 ? round($wrench / $classified, 2) : null,
            'classified_failure_count' => $classified,
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
     *
     * The plant has a single clock: this is the *union* of the stoppage intervals,
     * clipped to the window. Two paros that overlap cost their combined span once,
     * and a paro straddling the month boundary only charges this month the part
     * that happened this month.
     *
     * Beware when reading `maintenance_lost_hours` against `lost_hours`: because
     * both are unions, a maintenance paro overlapping a «falta de fruta» paro means
     * the parts do not add up to the whole. That is the honest answer — those hours
     * were lost once, and two areas can both claim them.
     */
    public function lostHours(
        Plant $plant,
        CarbonInterface $from,
        CarbonInterface $to,
        bool $maintenanceOnly = false,
        bool $includePlanned = true,
    ): float {
        $query = $this->eventsFor($plant)->where('affects_production', true);

        if ($maintenanceOnly) {
            $query->maintenanceOwned();

            if (! $includePlanned) {
                $query->where('was_planned', false);
            }
        }

        return $this->lostHours->unionHours($query, $from, $to);
    }

    /** @return Builder<EquipmentDowntimeEvent> */
    private function eventsFor(Plant $plant): Builder
    {
        return EquipmentDowntimeEvent::withoutGlobalScopes()->where('plant_id', $plant->id);
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
            ->maintenanceOwned()
            ->whereBetween('started_at', [$from, $to])
            ->count();
    }

    /**
     * Las dos cuentas de fallas, y la distancia entre ellas.
     *
     * `reported` es la de la planta: cuenta los paros que **ellos** marcaron Tipo I
     * «Mantenimiento». `actual` es la nuestra: cuenta los paros cuya **causa física**
     * es mecánica, eléctrica o de instrumentación, sin importar a quién le echaron
     * la culpa en la planilla.
     *
     * En junio 2026 esa diferencia es de 88 fallas: hay 88 paros con causa «falla
     * mecánica» o «falla eléctrica» clasificados Tipo I «Operativa», y el MTBF que
     * la planta reporta los excluye a todos. Su indicador sale ~3 veces mejor de lo
     * que la planta está.
     *
     * Este método existe para que ese hueco se pueda enseñar en una reunión con el
     * paro que lo causa en la mano, en vez de que Fronda muestre un número peor que
     * el suyo sin poder explicar por qué.
     *
     * @return array{
     *     reported_failure_count: int,
     *     actual_failure_count: int,
     *     unattributed_failure_count: int,
     *     reported_mtbf_hours: ?float,
     *     actual_mtbf_hours: ?float,
     * }
     */
    public function failureAttributionGap(Plant $plant, CarbonInterface $from, CarbonInterface $to): array
    {
        $window = fn (): Builder => $this->eventsFor($plant)
            ->where('was_planned', false)
            ->whereBetween('started_at', [$from, $to]);

        // Lo que la planta le atribuye a mantenimiento, con su propio criterio.
        $reported = (clone $window())
            ->where('reported_type', ReportedStoppageType::Maintenance->value)
            ->count();

        // Lo que realmente falló, según la causa física del paro.
        $actual = (clone $window())->maintenanceOwned()->count();

        $effective = max(0.0, round(
            $this->programmedHours($plant, $from, $to) - $this->lostHours($plant, $from, $to),
            2,
        ));

        return [
            'reported_failure_count' => $reported,
            'actual_failure_count' => $actual,
            // Las fallas que la planta no se cobra a sí misma: el hueco.
            'unattributed_failure_count' => max(0, $actual - $reported),
            'reported_mtbf_hours' => $reported > 0 ? round($effective / $reported, 2) : null,
            'actual_mtbf_hours' => $actual > 0 ? round($effective / $actual, 2) : null,
        ];
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
}
