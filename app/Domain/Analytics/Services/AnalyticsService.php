<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\DTOs\TrendPoint;
use App\Models\EquipmentDowntimeEvent;
use App\Models\EquipmentKpi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    // ── Internal helpers ──────────────────────────────────────────────────────

    private function fetchRawMonthlyEventStats(string $tenantId): Collection
    {
        $since = now()->startOfMonth()->subMonths(11);

        $dbRows = EquipmentDowntimeEvent::withoutGlobalScopes()
            ->selectRaw("
                TO_CHAR(DATE_TRUNC('month', started_at), 'YYYY-MM') AS month_key,
                COUNT(*) FILTER (WHERE was_planned = false) AS failure_count,
                COALESCE(SUM(duration_minutes) FILTER (WHERE was_planned = false), 0) AS failure_minutes,
                COALESCE(SUM(duration_minutes), 0) AS total_downtime_minutes
            ")
            ->where('tenant_id', $tenantId)
            ->where('started_at', '>=', $since)
            ->whereNotNull('ended_at')
            ->groupByRaw("DATE_TRUNC('month', started_at)")
            ->get()
            ->keyBy('month_key');

        // Fill all 12 months, including months with no events
        return collect(range(0, 11))
            ->map(fn ($i) => now()->startOfMonth()->subMonths(11 - $i))
            ->map(function ($date) use ($dbRows) {
                $key = $date->format('Y-m');
                $row = $dbRows->get($key);

                return [
                    'label' => $date->format('M Y'),
                    'failure_count' => (int) ($row?->failure_count ?? 0),
                    'failure_minutes' => (float) ($row?->failure_minutes ?? 0),
                    'total_downtime_minutes' => (float) ($row?->total_downtime_minutes ?? 0),
                    'days_in_month' => (int) $date->daysInMonth,
                ];
            });
    }

    private function monthlyEventStats(string $tenantId): Collection
    {
        $key = "analytics:monthly_events:{$tenantId}";

        try {
            return Cache::remember(
                $key,
                now()->addHour(),
                fn () => $this->fetchRawMonthlyEventStats($tenantId)
            );
        } catch (\Throwable) {
            Cache::forget($key);

            return $this->fetchRawMonthlyEventStats($tenantId);
        }
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /** @return TrendPoint[] — unplanned failures per month, last 12 months */
    public function failuresByMonth(string $tenantId): array
    {
        return $this->monthlyEventStats($tenantId)
            ->map(fn ($row) => new TrendPoint(
                label: $row['label'],
                value: (float) $row['failure_count'],
                count: $row['failure_count'],
            ))
            ->values()
            ->all();
    }

    /** @return TrendPoint[] — total downtime hours per month, last 12 months */
    public function downtimeTrend(string $tenantId): array
    {
        return $this->monthlyEventStats($tenantId)
            ->map(fn ($row) => new TrendPoint(
                label: $row['label'],
                value: round($row['total_downtime_minutes'] / 60, 2),
            ))
            ->values()
            ->all();
    }

    /**
     * @return TrendPoint[] — tenant-wide MTBF (hours) per month.
     *                      null when there are no failures in a month (gap in chart, not zero).
     *                      MTBF = (hours_in_month − downtime_hours) / failure_count
     */
    public function mtbfTrend(string $tenantId): array
    {
        return $this->monthlyEventStats($tenantId)
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
     * @return TrendPoint[] — tenant-wide MTTR (hours) per month.
     *                      null when there are no failures in a month (gap in chart, not zero).
     *                      MTTR = failure_downtime_hours / failure_count
     */
    public function mttrTrend(string $tenantId): array
    {
        return $this->monthlyEventStats($tenantId)
            ->map(function ($row) {
                if ($row['failure_count'] === 0) {
                    return new TrendPoint(label: $row['label'], value: null);
                }

                $mttr = $row['failure_minutes'] / 60 / $row['failure_count'];

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
}
