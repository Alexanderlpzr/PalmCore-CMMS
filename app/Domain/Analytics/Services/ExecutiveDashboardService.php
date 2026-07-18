<?php

namespace App\Domain\Analytics\Services;

use App\Models\Area;
use App\Models\EquipmentKpi;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El resumen ejecutivo, calculado una sola vez.
 *
 * Antes vivía entero dentro de ExecutiveDashboardController — cada método era
 * la implementación real, no una capa fina sobre un servicio. Se extrae aquí
 * para que el Dashboard de Filament (Resumen Ejecutivo) pueda usar exactamente
 * los mismos números que ya consumía la API, sin mantener la consulta dos
 * veces en paralelo.
 */
class ExecutiveDashboardService
{
    /**
     * @return array{availability: float, mtbf_hours: float, mttr_hours: float, open_work_orders: int, overdue_preventives: int, monthly_cost: float}
     */
    public function summary(string $tenantId): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        // KPI averages — prefer fresh records; fall back to all non-deleted
        $freshKpis = EquipmentKpi::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('is_stale', false)
            ->selectRaw('AVG(availability_percentage) as avg_availability, AVG(mtbf_hours) as avg_mtbf, AVG(mttr_hours) as avg_mttr')
            ->first();

        $hasFreshKpis = $freshKpis && $freshKpis->avg_availability !== null;

        if (! $hasFreshKpis) {
            $freshKpis = EquipmentKpi::where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->selectRaw('AVG(availability_percentage) as avg_availability, AVG(mtbf_hours) as avg_mtbf, AVG(mttr_hours) as avg_mttr')
                ->first();
        }

        $openWorkOrders = WorkOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['draft', 'planned', 'in_progress', 'on_hold'])
            ->count();

        $overduePreventives = WorkOrder::where('tenant_id', $tenantId)
            ->where('work_order_type', 'preventive')
            ->whereIn('status', ['draft', 'planned'])
            ->where('planned_end_at', '<', now())
            ->count();

        $monthlyCost = WorkOrder::where('tenant_id', $tenantId)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $startOfMonth)
            ->sum('actual_cost_total');

        return [
            'availability' => round((float) ($freshKpis?->avg_availability ?? 0), 2),
            'mtbf_hours' => round((float) ($freshKpis?->avg_mtbf ?? 0), 2),
            'mttr_hours' => round((float) ($freshKpis?->avg_mttr ?? 0), 2),
            'open_work_orders' => $openWorkOrders,
            'overdue_preventives' => $overduePreventives,
            'monthly_cost' => round((float) $monthlyCost, 2),
        ];
    }

    /**
     * @return list<array{code: string, name: string, availability: float, failure_count: int, mttr_hours: float, monthly_cost: float}>
     */
    public function areas(string $tenantId): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        $areas = Area::where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->get();

        $areaIds = $areas->pluck('id');

        // Aggregate KPIs per area in a single grouped query
        $kpiByArea = DB::table('equipment_kpis')
            ->join('equipment', 'equipment_kpis.equipment_id', '=', 'equipment.id')
            ->whereNull('equipment_kpis.deleted_at')
            ->whereIn('equipment.area_id', $areaIds)
            ->groupBy('equipment.area_id')
            ->select(
                'equipment.area_id',
                DB::raw('AVG(equipment_kpis.availability_percentage) as avg_availability'),
                DB::raw('SUM(equipment_kpis.failure_count) as total_failures'),
                DB::raw('AVG(equipment_kpis.mttr_hours) as avg_mttr')
            )
            ->get()
            ->keyBy('area_id');

        // Monthly costs per area in a single grouped query
        $costByArea = WorkOrder::where('tenant_id', $tenantId)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $startOfMonth)
            ->whereIn('area_id', $areaIds)
            ->groupBy('area_id')
            ->select('area_id', DB::raw('SUM(actual_cost_total) as total_cost'))
            ->get()
            ->keyBy('area_id');

        return $areas->map(function (Area $area) use ($kpiByArea, $costByArea): array {
            $kpi = $kpiByArea->get($area->id);
            $cost = $costByArea->get($area->id);

            return [
                'code' => $area->code,
                'name' => $area->name,
                'availability' => round((float) ($kpi?->avg_availability ?? 0), 2),
                'failure_count' => (int) ($kpi?->total_failures ?? 0),
                'mttr_hours' => round((float) ($kpi?->avg_mttr ?? 0), 2),
                'monthly_cost' => round((float) ($cost?->total_cost ?? 0), 2),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{id: string, code: ?string, name: ?string, area_code: ?string, area_name: ?string, failure_count: int, downtime_hours: float, monthly_cost: float}>
     */
    public function topEquipment(string $tenantId): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        $kpis = EquipmentKpi::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with(['equipment.area'])
            ->orderByDesc('failure_count')
            ->limit(10)
            ->get();

        $equipmentIds = $kpis->pluck('equipment_id')->filter()->unique();

        // Monthly cost per equipment in a single query
        $costByEquipment = WorkOrder::where('tenant_id', $tenantId)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $startOfMonth)
            ->whereIn('equipment_id', $equipmentIds)
            ->groupBy('equipment_id')
            ->select('equipment_id', DB::raw('SUM(actual_cost_total) as total_cost'))
            ->get()
            ->keyBy('equipment_id');

        return $kpis->map(function (EquipmentKpi $kpi) use ($costByEquipment): array {
            $equipment = $kpi->equipment;
            $area = $equipment?->area;
            $cost = $costByEquipment->get($kpi->equipment_id);

            return [
                'id' => $kpi->equipment_id,
                'code' => $equipment?->code,
                'name' => $equipment?->name,
                'area_code' => $area?->code,
                'area_name' => $area?->name,
                'failure_count' => (int) ($kpi->failure_count ?? 0),
                'downtime_hours' => round((float) ($kpi->downtime_hours ?? 0), 2),
                'monthly_cost' => round((float) ($cost?->total_cost ?? 0), 2),
            ];
        })->values()->all();
    }

    /**
     * @return array{corrective: float, preventive: float, predictive: float, other: float, total: float, period_start: string, period_end: string}
     */
    public function costs(string $tenantId): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        $rows = WorkOrder::where('tenant_id', $tenantId)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('work_order_type, SUM(actual_cost_total) as total')
            ->groupBy('work_order_type')
            ->get()
            ->keyBy('work_order_type');

        $corrective = (float) ($rows->get('corrective')?->total ?? 0)
            + (float) ($rows->get('emergency')?->total ?? 0);

        $preventive = (float) ($rows->get('preventive')?->total ?? 0);
        $predictive = (float) ($rows->get('predictive')?->total ?? 0);
        $other = (float) ($rows->get('improvement')?->total ?? 0);

        $total = $corrective + $preventive + $predictive + $other;

        return [
            'corrective' => round($corrective, 2),
            'preventive' => round($preventive, 2),
            'predictive' => round($predictive, 2),
            'other' => round($other, 2),
            'total' => round($total, 2),
            'period_start' => $startOfMonth->toDateString(),
            'period_end' => $endOfMonth->toDateString(),
        ];
    }

    /**
     * @return list<array{month: string, availability: ?float, mtbf_hours: ?float, mttr_hours: ?float, cost: float}>
     */
    public function trends(string $tenantId): array
    {
        // KPI metrics grouped by month
        $kpiRows = DB::table('equipment_kpis')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('period_start', '>=', Carbon::now()->subMonths(11)->startOfMonth())
            ->selectRaw("TO_CHAR(period_start, 'YYYY-MM') as month, AVG(availability_percentage) as avg_availability, AVG(mtbf_hours) as avg_mtbf, AVG(mttr_hours) as avg_mttr")
            ->groupByRaw("TO_CHAR(period_start, 'YYYY-MM')")
            ->get()
            ->keyBy('month');

        // Costs grouped by month
        $costRows = DB::table('work_orders')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', Carbon::now()->subMonths(11)->startOfMonth())
            ->selectRaw("TO_CHAR(completed_at, 'YYYY-MM') as month, SUM(actual_cost_total) as total_cost")
            ->groupByRaw("TO_CHAR(completed_at, 'YYYY-MM')")
            ->get()
            ->keyBy('month');

        // Build 12-month list oldest-first
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = Carbon::now()->subMonths($i)->format('Y-m');
        }

        return array_map(function (string $month) use ($kpiRows, $costRows): array {
            $kpi = $kpiRows->get($month);
            $cost = $costRows->get($month);

            return [
                'month' => $month,
                'availability' => $kpi ? round((float) $kpi->avg_availability, 2) : null,
                'mtbf_hours' => $kpi ? round((float) $kpi->avg_mtbf, 2) : null,
                'mttr_hours' => $kpi ? round((float) $kpi->avg_mttr, 2) : null,
                'cost' => round((float) ($cost?->total_cost ?? 0), 2),
            ];
        }, $months);
    }
}
