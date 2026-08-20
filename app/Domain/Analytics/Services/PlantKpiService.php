<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Services\LostHoursCalculator;
use App\Domain\Energy\Enums\EnergySource;
use App\Models\EnergyMeter;
use App\Models\EnergyMeterReading;
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
 * Los tres números que la planta reporta cada mes, con el vocabulario de su
 * propia planilla:
 *
 *     HP    horas pagadas          → `programmed_hours`, del calendario de producción
 *     HASEO horas de aseo          → paros de mantenimiento *programados*
 *     HMTTO horas de paro por mtto → paros de mantenimiento *no* programados
 *     HOPER otras paradas          → el resto de las horas perdidas
 *     HPREN horas de prensado      → `effective_hours` = HP − todas las perdidas
 *     FP    fruta procesada        → toneladas del calendario de producción
 *
 *     Eficiencia     = HPREN / (HP − HASEO)
 *     Productividad  = FP    / (HP − HASEO)
 *     Disponibilidad = (HP − HASEO − HMTTO) / HP
 *
 * El denominador de las dos primeras excluye el aseo a propósito: son las horas
 * en que la planta *podía* prensar. La disponibilidad sí lo incluye, porque
 * responde otra pregunta —de las horas que pagué, cuántas tuve la planta
 * disponible— y una parada de aseo también es planta parada.
 *
 * MTBF and MTTR are computed on the same basis — over the plant's *effective*
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
     *     cleaning_hours: float,
     *     other_lost_hours: float,
     *     processed_tons: float,
     *     efficiency_percentage: ?float,
     *     productivity_tons_per_hour: ?float,
     *     availability_percentage: ?float,
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
        $cleaning = $this->cleaningHours($plant, $from, $to);
        $tons = $this->processedTons($plant, $from, $to);

        // El denominador de eficiencia y productividad: las horas en que la
        // planta podía prensar. Con piso en cero — un mes en que el aseo se comió
        // todas las horas pagadas no tiene indicador, no tiene uno negativo.
        $pressable = max(0.0, round($programmed - $cleaning, 2));
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
            'cleaning_hours' => $cleaning,
            // HOPER: lo que no fue aseo ni mantenimiento. Se deriva por resta
            // sobre la unión, no sumando paros, para que dos paros solapados no
            // se cuenten dos veces y el desglose siempre cierre contra `lost_hours`.
            'other_lost_hours' => max(0.0, round($lost - $maintenanceLost, 2)),
            'processed_tons' => $tons,
            'efficiency_percentage' => $pressable > 0
                ? round($effective / $pressable * 100, 2)
                : null,
            'productivity_tons_per_hour' => $pressable > 0
                ? round($tons / $pressable, 2)
                : null,
            'availability_percentage' => $programmed > 0
                ? round(($programmed - $maintenanceLost) / $programmed * 100, 2)
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
     * FP — la fruta que entró, del mismo calendario que declara las horas.
     *
     * Vive junto a `programmed_hours` porque el planificador cierra el día una
     * sola vez: separarlo en otra pantalla garantizaba que una de las dos cifras
     * se quedara sin llenar.
     */
    public function processedTons(Plant $plant, CarbonInterface $from, CarbonInterface $to): float
    {
        return round((float) ProductionCalendarDay::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->whereBetween('calendar_date', [$from->toDateString(), $to->toDateString()])
            ->sum('processed_tons'), 2);
    }

    /**
     * HASEO — aseo y mantenimiento preventivo.
     *
     * Es el paro de mantenimiento que estaba en el plan: `was_planned`. No se
     * captura a mano en ningún lado, se lee de los paros que la planta ya
     * registra, así que el indicador nunca puede desmentir a la planilla de paros.
     *
     * El corolario incómodo vale decirlo: un preventivo que nadie registró como
     * paro no existe para este número, y la eficiencia del mes sale peor de lo
     * que fue. El arreglo es registrar el paro, no capturar la hora aparte.
     */
    public function cleaningHours(Plant $plant, CarbonInterface $from, CarbonInterface $to): float
    {
        return $this->lostHours->unionHours(
            $this->eventsFor($plant)
                ->where('affects_production', true)
                ->where('was_planned', true)
                ->maintenanceOwned(),
            $from,
            $to,
        );
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
     *
     * Con una excepción: si alguien corrigió las toneladas a mano, recalcular no
     * se las lleva por delante. Báscula y laboratorio rara vez coinciden al cierre,
     * y el mes se vuelve a calcular cada vez que entra un paro atrasado — sin esta
     * guarda, la corrección duraría hasta el siguiente recálculo.
     */
    public function snapshotMonth(Plant $plant, int $year, int $month): PlantMonthlyKpi
    {
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $metrics = $this->calculate($plant, $from, $to);

        $existing = PlantMonthlyKpi::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $keepsManualTons = $existing?->processed_tons_is_manual === true;

        // Los meses que vinieron de la hoja histórica no se recalculan: sus lecturas
        // diarias nunca existieron, así que recalcular los pondría en cero. Misma guarda
        // que la de las toneladas corregidas a mano, y por el mismo motivo.
        $keepsImportedEnergy = $existing?->energy_is_imported === true;

        $energy = $keepsImportedEnergy
            ? [
                'kwh_grid' => $existing->kwh_grid,
                'kwh_genset' => $existing->kwh_genset,
                'kwh_turbine' => $existing->kwh_turbine,
            ]
            : $this->energyBySource($plant, $from, $to);

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
                'cleaning_hours' => $metrics['cleaning_hours'],
                'processed_tons' => $keepsManualTons
                    ? $existing->processed_tons
                    : $metrics['processed_tons'],
                ...$energy,
                'energy_is_imported' => $keepsImportedEnergy,
                'failure_count' => $metrics['failure_count'],
                'mtbf_hours' => $metrics['mtbf_hours'],
                'mttr_hours' => $metrics['mttr_hours'],
                'calculated_at' => now(),
            ],
        )->refresh();
    }

    /**
     * El consumo eléctrico de un período, mes a mes, venga de donde venga el dato.
     *
     * Hay dos orígenes y hay que servir a los dos: los meses de 2024 y 2025 se cargaron
     * del Excel como total mensual y no tienen ni una lectura diaria detrás, mientras que
     * el mes en curso solo existe como lecturas. Por cada mes se prefiere la fila del
     * cierre cuando trae energía, y si no, se suman las lecturas.
     *
     * Las toneladas salen de la misma fila del cierre, y si no hay, del calendario de
     * producción — el mismo denominador que la productividad en t/h, sin capturarlo dos
     * veces.
     *
     * @return array{
     *     kwh_grid: ?float, kwh_genset: ?float, kwh_turbine: ?float,
     *     kwh_total: ?float, processed_tons: float,
     *     kwh_per_ton: ?float, clean_energy_percentage: ?float,
     * }
     */
    public function energySummary(Plant $plant, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = PlantMonthlyKpi::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->get()
            ->keyBy(fn (PlantMonthlyKpi $kpi): string => $kpi->year.'-'.$kpi->month);

        $totals = ['kwh_grid' => null, 'kwh_genset' => null, 'kwh_turbine' => null];
        $tons = 0.0;

        $cursor = Carbon::parse($from)->startOfMonth();
        $last = Carbon::parse($to)->startOfMonth();

        while ($cursor->lte($last)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();
            $row = $rows->get($cursor->year.'-'.$cursor->month);

            $hasStoredEnergy = $row !== null
                && ($row->kwh_grid !== null || $row->kwh_genset !== null || $row->kwh_turbine !== null);

            $energy = $hasStoredEnergy
                ? ['kwh_grid' => $row->kwh_grid, 'kwh_genset' => $row->kwh_genset, 'kwh_turbine' => $row->kwh_turbine]
                : $this->energyBySource($plant, $monthStart, $monthEnd);

            foreach ($totals as $key => $value) {
                if ($energy[$key] !== null) {
                    $totals[$key] = round((float) ($value ?? 0) + (float) $energy[$key], 1);
                }
            }

            $tons += $row?->processed_tons !== null
                ? (float) $row->processed_tons
                : $this->processedTons($plant, $monthStart, $monthEnd);

            $cursor->addMonth();
        }

        $known = array_filter($totals, fn (?float $v): bool => $v !== null);
        $total = $known === [] ? null : round(array_sum($known), 1);
        $tons = round($tons, 2);

        return [
            ...$totals,
            'kwh_total' => $total,
            'processed_tons' => $tons,
            'kwh_per_ton' => ($total !== null && $tons > 0) ? round($total / $tons, 2) : null,
            // Sin dato de turbina no hay porcentaje: decir 0 % afirmaría que no generó
            // nada, que es justo lo que no sabemos de cinco meses de 2025.
            'clean_energy_percentage' => ($totals['kwh_turbine'] !== null && $total > 0)
                ? round($totals['kwh_turbine'] / $total * 100, 2)
                : null,
        ];
    }

    /**
     * Los kWh del período, separados por fuente.
     *
     * `null` —no cero— cuando un contador no tiene ni una lectura en el rango: cero kWh
     * de turbina dice que la planta funcionó a diésel, y no saberlo dice que nadie lo
     * anotó. La hoja histórica trae cinco meses de 2025 exactamente en ese caso.
     *
     * Se suman los deltas y no se restan los extremos: si cambiaron el contador dentro
     * del mes, la resta daría un número absurdo y el delta ya trae resuelto ese caso.
     *
     * @return array{kwh_grid: ?float, kwh_genset: ?float, kwh_turbine: ?float}
     */
    public function energyBySource(Plant $plant, CarbonInterface $from, CarbonInterface $to): array
    {
        $meters = EnergyMeter::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->get();

        $totals = [
            EnergySource::Grid->value => null,
            EnergySource::Genset->value => null,
            EnergySource::Turbine->value => null,
        ];

        foreach ($meters as $meter) {
            $readings = EnergyMeterReading::withoutGlobalScopes()
                ->where('energy_meter_id', $meter->id)
                ->whereBetween('reading_date', [$from->toDateString(), $to->toDateString()]);

            if (! $readings->exists()) {
                continue;
            }

            $source = $meter->source->value;
            $totals[$source] = round((float) ($totals[$source] ?? 0) + (float) $readings->sum('delta'), 1);
        }

        return [
            'kwh_grid' => $totals[EnergySource::Grid->value],
            'kwh_genset' => $totals[EnergySource::Genset->value],
            'kwh_turbine' => $totals[EnergySource::Turbine->value],
        ];
    }
}
