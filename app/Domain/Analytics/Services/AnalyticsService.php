<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\DTOs\TrendPoint;
use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Maintenance\Enums\FailureMode;
use App\Models\EquipmentDowntimeEvent;
use App\Models\EquipmentKpi;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /** Hard cap on how many months a single query will ever fill/group, regardless of the requested range. */
    private const MAX_MONTHS = 60;

    // ── Internal helpers ──────────────────────────────────────────────────────

    /**
     * @param  ?CarbonInterface  $from  month-aligned start; defaults to 11 months before $to
     * @param  ?CarbonInterface  $to  month-aligned end; defaults to the current month
     * @param  ?string  $equipmentId  when given, scopes the stats to a single equipment instead of the whole tenant
     */
    private function fetchRawMonthlyEventStats(string $tenantId, ?CarbonInterface $from, ?CarbonInterface $to, ?string $equipmentId = null): Collection
    {
        $to = CarbonImmutable::parse($to ?? now())->startOfMonth();
        $from = CarbonImmutable::parse($from ?? $to->subMonths(11))->startOfMonth();

        $months = collect();
        $cursor = $from;
        while ($cursor->lte($to) && $months->count() < self::MAX_MONTHS) {
            $months->push($cursor);
            $cursor = $cursor->addMonth();
        }

        $dbRows = EquipmentDowntimeEvent::withoutGlobalScopes()
            ->selectRaw("
                TO_CHAR(DATE_TRUNC('month', started_at), 'YYYY-MM') AS month_key,
                COUNT(*) FILTER (WHERE was_planned = false) AS failure_count,
                COUNT(*) FILTER (WHERE was_planned = false AND duration_minutes > 0) AS downtime_failure_count,
                COALESCE(SUM(duration_minutes) FILTER (WHERE was_planned = false), 0) AS failure_minutes,
                COALESCE(SUM(duration_minutes), 0) AS total_downtime_minutes
            ")
            ->where('tenant_id', $tenantId)
            ->when($equipmentId !== null, fn ($query) => $query->where('equipment_id', $equipmentId))
            ->where('started_at', '>=', $from)
            ->where('started_at', '<', $to->addMonth())
            ->whereNotNull('ended_at')
            ->groupByRaw("DATE_TRUNC('month', started_at)")
            ->get()
            ->keyBy('month_key');

        // Fill every month in the range, including months with no events
        return $months->map(function (CarbonImmutable $date) use ($dbRows) {
            $key = $date->format('Y-m');
            $row = $dbRows->get($key);

            return [
                'label' => $date->format('M Y'),
                'failure_count' => (int) ($row?->failure_count ?? 0),
                'downtime_failure_count' => (int) ($row?->downtime_failure_count ?? 0),
                'failure_minutes' => (float) ($row?->failure_minutes ?? 0),
                'total_downtime_minutes' => (float) ($row?->total_downtime_minutes ?? 0),
                'days_in_month' => (int) $date->daysInMonth,
            ];
        });
    }

    private function monthlyEventStats(string $tenantId, ?CarbonInterface $from = null, ?CarbonInterface $to = null, ?string $equipmentId = null): Collection
    {
        $rangeKey = ($from ? CarbonImmutable::parse($from)->format('Y-m') : 'default')
            .':'.($to ? CarbonImmutable::parse($to)->format('Y-m') : 'default');
        $key = "analytics:monthly_events:{$tenantId}:".($equipmentId ?? 'all').":{$rangeKey}";

        try {
            return Cache::remember(
                $key,
                now()->addHour(),
                fn () => $this->fetchRawMonthlyEventStats($tenantId, $from, $to, $equipmentId)
            );
        } catch (\Throwable) {
            Cache::forget($key);

            return $this->fetchRawMonthlyEventStats($tenantId, $from, $to, $equipmentId);
        }
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * @return TrendPoint[] — unplanned failures per month.
     *                      $from/$to default to the trailing 12 months when omitted.
     */
    public function failuresByMonth(string $tenantId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        return $this->monthlyEventStats($tenantId, $from, $to)
            ->map(fn ($row) => new TrendPoint(
                label: $row['label'],
                value: (float) $row['failure_count'],
                count: $row['failure_count'],
            ))
            ->values()
            ->all();
    }

    /**
     * @return TrendPoint[] — total downtime hours per month.
     *                      $from/$to default to the trailing 12 months when omitted.
     */
    public function downtimeTrend(string $tenantId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        return $this->monthlyEventStats($tenantId, $from, $to)
            ->map(fn ($row) => new TrendPoint(
                label: $row['label'],
                value: round($row['total_downtime_minutes'] / 60, 2),
            ))
            ->values()
            ->all();
    }

    /**
     * @return TrendPoint[] — total downtime hours grouped by reported_type (el "Tipo I"
     *                      de la planta: quién paró la línea — programada, mantenimiento,
     *                      operativa, externa — no qué se rompió). $from/$to default to
     *                      the trailing 12 months when omitted.
     */
    public function downtimeByReportedType(string $tenantId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        return $this->downtimeByColumn($tenantId, 'reported_type', ReportedStoppageType::class, $from, $to);
    }

    /**
     * @return TrendPoint[] — total downtime hours grouped by stoppage_category (la
     *                      causa física: mecánico, eléctrico, falta de fruta…). $from/$to
     *                      default to the trailing 12 months when omitted.
     */
    public function downtimeByStoppageCategory(string $tenantId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        return $this->downtimeByColumn($tenantId, 'stoppage_category', StoppageCategory::class, $from, $to);
    }

    /**
     * @param  class-string<ReportedStoppageType|StoppageCategory>  $enumClass
     * @return TrendPoint[]
     */
    private function downtimeByColumn(string $tenantId, string $column, string $enumClass, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $to = CarbonImmutable::parse($to ?? now())->startOfMonth();
        $from = CarbonImmutable::parse($from ?? $to->subMonths(11))->startOfMonth();

        $key = "analytics:downtime_by_{$column}:{$tenantId}:{$from->format('Y-m')}:{$to->format('Y-m')}";

        return Cache::remember(
            $key,
            now()->addMinutes(20),
            function () use ($tenantId, $column, $enumClass, $from, $to): array {
                return DB::table('equipment_downtime_events')
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull($column)
                    ->whereNotNull('ended_at')
                    ->where('started_at', '>=', $from)
                    ->where('started_at', '<', $to->addMonth())
                    ->selectRaw("{$column} AS bucket, COALESCE(SUM(duration_minutes), 0) AS total_minutes")
                    ->groupBy($column)
                    ->orderByDesc('total_minutes')
                    ->get()
                    ->map(fn ($row) => new TrendPoint(
                        label: $enumClass::tryFrom((string) $row->bucket)?->label() ?? (string) $row->bucket,
                        value: round(((float) $row->total_minutes) / 60, 2),
                    ))
                    ->all();
            }
        );
    }

    /**
     * @return TrendPoint[] — tenant-wide (or single-equipment) MTBF (hours) per month.
     *                      null when there are no failures in a month (gap in chart, not zero).
     *                      MTBF = (hours_in_month − downtime_hours) / failure_count
     *                      $from/$to default to the trailing 12 months when omitted.
     *                      $equipmentId scopes the trend to a single equipment when given.
     */
    public function mtbfTrend(string $tenantId, ?CarbonInterface $from = null, ?CarbonInterface $to = null, ?string $equipmentId = null): array
    {
        return $this->monthlyEventStats($tenantId, $from, $to, $equipmentId)
            ->map(function ($row) {
                if ($row['failure_count'] === 0) {
                    return new TrendPoint(label: $row['label'], value: null);
                }

                $hoursInMonth = $row['days_in_month'] * 24;
                $downtimeHours = $row['failure_minutes'] / 60;
                $mtbf = max(0, ($hoursInMonth - $downtimeHours) / $row['failure_count']);

                return new TrendPoint(
                    label: $row['label'],
                    value: round($mtbf, 2),
                    count: $row['failure_count'],
                );
            })
            ->values()
            ->all();
    }

    /**
     * @return TrendPoint[] — tenant-wide (or single-equipment) MTTR (hours) per month.
     *                      null when there are no failures with downtime in a month
     *                      (gap in chart, not zero).
     *                      MTTR = failure_downtime_hours / failures_that_stopped_the_equipment
     *                      $from/$to default to the trailing 12 months when omitted.
     *                      $equipmentId scopes the trend to a single equipment when given.
     */
    public function mttrTrend(string $tenantId, ?CarbonInterface $from = null, ?CarbonInterface $to = null, ?string $equipmentId = null): array
    {
        return $this->monthlyEventStats($tenantId, $from, $to, $equipmentId)
            ->map(function ($row) {
                // Divide only by failures that caused downtime — a failure fixed
                // in marcha (zero downtime) must not drag the mean repair time down.
                if ($row['downtime_failure_count'] === 0) {
                    return new TrendPoint(label: $row['label'], value: null);
                }

                $mttr = $row['failure_minutes'] / 60 / $row['downtime_failure_count'];

                return new TrendPoint(
                    label: $row['label'],
                    value: round($mttr, 2),
                    count: $row['failure_count'],
                );
            })
            ->values()
            ->all();
    }

    /** @return TrendPoint[] — top 10 equipment by total work order cost (all time) */
    public function costByEquipment(string $tenantId): array
    {
        return Cache::remember(
            "analytics:cost_by_equipment:{$tenantId}",
            now()->addMinutes(20),
            function () use ($tenantId): array {
                return DB::table('work_orders as wo')
                    ->join('equipment as e', 'wo.equipment_id', '=', 'e.id')
                    ->where('wo.tenant_id', $tenantId)
                    ->whereNull('wo.deleted_at')
                    ->whereNull('e.deleted_at')
                    ->whereNotNull('wo.actual_cost_total')
                    ->whereNotNull('wo.equipment_id')
                    ->selectRaw('e.name AS equipment_name, SUM(wo.actual_cost_total) AS total_cost')
                    ->groupBy('e.id', 'e.name')
                    ->orderByDesc('total_cost')
                    ->limit(10)
                    ->get()
                    ->map(fn ($row) => new TrendPoint(
                        label: $row->equipment_name,
                        value: round((float) $row->total_cost, 2),
                    ))
                    ->all();
            }
        );
    }

    /** @return TrendPoint[] — top 10 equipment by unplanned failures, last 12 months */
    public function paretoFailures(string $tenantId): array
    {
        return Cache::remember(
            "analytics:pareto_failures:{$tenantId}",
            now()->addMinutes(20),
            function () use ($tenantId): array {
                $since = now()->subMonths(12);

                return DB::table('equipment_downtime_events as ede')
                    ->join('equipment as e', 'ede.equipment_id', '=', 'e.id')
                    ->where('ede.tenant_id', $tenantId)
                    ->where('ede.was_planned', false)
                    ->where('ede.started_at', '>=', $since)
                    ->whereNull('e.deleted_at')
                    ->selectRaw('e.name AS equipment_name, COUNT(*) AS failure_count')
                    ->groupBy('e.id', 'e.name')
                    ->orderByDesc('failure_count')
                    ->limit(10)
                    ->get()
                    ->map(fn ($row) => new TrendPoint(
                        label: $row->equipment_name,
                        value: (float) $row->failure_count,
                        count: (int) $row->failure_count,
                    ))
                    ->all();
            }
        );
    }

    /**
     * @return TrendPoint[] — unplanned failures grouped by failure mode, last 12 months.
     *                      Answers "which physical cause dominates" (bearing, seal,
     *                      electrical…) across the whole plant, for RCA — the
     *                      complement to paretoFailures() which is per-equipment.
     */
    public function paretoFailuresByMode(string $tenantId): array
    {
        return Cache::remember(
            "analytics:pareto_failure_modes:{$tenantId}",
            now()->addMinutes(20),
            function () use ($tenantId): array {
                $since = now()->subMonths(12);

                return DB::table('equipment_downtime_events')
                    ->where('tenant_id', $tenantId)
                    ->where('was_planned', false)
                    ->whereNotNull('failure_mode')
                    ->where('started_at', '>=', $since)
                    ->selectRaw('failure_mode, COUNT(*) AS failure_count')
                    ->groupBy('failure_mode')
                    ->orderByDesc('failure_count')
                    ->limit(15)
                    ->get()
                    ->map(fn ($row) => new TrendPoint(
                        label: FailureMode::tryFrom((string) $row->failure_mode)?->label() ?? (string) $row->failure_mode,
                        value: (float) $row->failure_count,
                        count: (int) $row->failure_count,
                    ))
                    ->all();
            }
        );
    }

    /**
     * @return array{best: TrendPoint[], worst: TrendPoint[]}
     *                                                        best: top 5 equipment by availability_percentage (descending)
     *                                                        worst: bottom 5 equipment by availability_percentage (ascending)
     */
    public function reliabilityRanking(string $tenantId): array
    {
        return Cache::remember(
            "analytics:reliability_ranking:{$tenantId}",
            now()->addMinutes(20),
            function () use ($tenantId): array {
                $baseQuery = fn () => EquipmentKpi::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('availability_percentage')
                    ->with('equipment');

                $toPoint = fn ($kpi) => new TrendPoint(
                    label: $kpi->equipment?->name ?? '—',
                    value: round((float) $kpi->availability_percentage, 2),
                );

                $best = $baseQuery()
                    ->orderByDesc('availability_percentage')
                    ->limit(5)
                    ->get()
                    ->map($toPoint)
                    ->all();

                $worst = $baseQuery()
                    ->orderBy('availability_percentage')
                    ->limit(5)
                    ->get()
                    ->map($toPoint)
                    ->all();

                return compact('best', 'worst');
            }
        );
    }

    /**
     * Point-in-time preventive-maintenance schedule adherence: of all active
     * plans that have a scheduled due date, how many are still on schedule
     * (next_due_at not past) vs overdue. The leading indicator an engineer
     * watches to know whether PM is keeping the plant out of failures.
     *
     * @return array{total: int, on_schedule: int, overdue: int, compliance: ?float}
     */
    public function preventiveCompliance(string $tenantId): array
    {
        // A plan is on schedule when it is overdue by neither time nor meter.
        // Time-based plans use next_due_at; meter-based plans compare next_due_meter
        // against the equipment's current reading. Hybrid plans must pass both.
        $row = DB::table('maintenance_schedules as ms')
            ->join('maintenance_plans as mp', 'ms.maintenance_plan_id', '=', 'mp.id')
            ->join('equipment as e', 'mp.equipment_id', '=', 'e.id')
            ->where('ms.tenant_id', $tenantId)
            ->where('mp.is_active', true)
            ->whereNull('mp.deleted_at')
            ->whereNull('e.deleted_at')
            ->where(function ($query): void {
                $query->whereNotNull('ms.next_due_at')->orWhereNotNull('ms.next_due_meter');
            })
            ->selectRaw('
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE
                    (ms.next_due_at IS NULL OR ms.next_due_at >= now())
                    AND (ms.next_due_meter IS NULL OR e.current_meter_reading IS NULL OR e.current_meter_reading < ms.next_due_meter)
                ) AS on_schedule
            ')
            ->first();

        $total = (int) ($row->total ?? 0);
        $onSchedule = (int) ($row->on_schedule ?? 0);

        return [
            'total' => $total,
            'on_schedule' => $onSchedule,
            'overdue' => $total - $onSchedule,
            'compliance' => $total > 0 ? round($onSchedule / $total * 100, 1) : null,
        ];
    }

    /**
     * Planned-vs-corrective mix of completed work over the trailing 12 months:
     * preventive/predictive (planned) against corrective/emergency (unplanned).
     * A healthy, proactive operation trends toward a high preventive share.
     * Improvement-type WOs are excluded (neither planned PM nor a failure).
     *
     * @return array{preventive: int, corrective: int, total: int, preventive_pct: ?float}
     */
    public function plannedVsCorrective(string $tenantId): array
    {
        $since = now()->subMonths(12);

        $row = DB::table('work_orders')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['completed', 'verified', 'closed'])
            ->where('completed_at', '>=', $since)
            ->selectRaw("
                COUNT(*) FILTER (WHERE work_order_type IN ('preventive', 'predictive')) AS preventive,
                COUNT(*) FILTER (WHERE work_order_type IN ('corrective', 'emergency')) AS corrective
            ")
            ->first();

        $preventive = (int) ($row->preventive ?? 0);
        $corrective = (int) ($row->corrective ?? 0);
        $total = $preventive + $corrective;

        return [
            'preventive' => $preventive,
            'corrective' => $corrective,
            'total' => $total,
            'preventive_pct' => $total > 0 ? round($preventive / $total * 100, 1) : null,
        ];
    }
}
