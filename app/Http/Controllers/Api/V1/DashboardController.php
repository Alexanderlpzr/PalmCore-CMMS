<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Alert;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Consolidated dashboard endpoints for the Ops SPA home screen.
 *
 * summary()  — 4 KPI counts, 2-minute cache.
 * activity() — recent WOs + comments + upcoming preventivos, 1-minute cache.
 * images()   — institutional photo stubs (ready for future upload feature).
 * novedades()— critical alerts + overdue/upcoming plans, 2-minute cache.
 */
class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $tenantId = CurrentTenant::id();

        $data = Cache::remember("dashboard:summary:{$tenantId}", 120, function () use ($tenantId) {
            $activeWOs = WorkOrder::where('tenant_id', $tenantId)
                ->whereIn('status', ['draft', 'planned', 'in_progress', 'on_hold'])
                ->count();

            $pendingMRs = MaintenanceRequest::where('tenant_id', $tenantId)
                ->whereIn('status', ['submitted', 'under_review'])
                ->count();

            $criticalAlerts = Alert::where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->whereIn('severity', ['critical', 'high'])
                ->count();

            $maintenanceEquipment = WorkOrder::where('tenant_id', $tenantId)
                ->whereIn('status', ['in_progress', 'planned'])
                ->where('work_order_type', 'preventive')
                ->distinct('equipment_id')
                ->count('equipment_id');

            return compact('activeWOs', 'pendingMRs', 'criticalAlerts', 'maintenanceEquipment');
        });

        return response()->json($data);
    }

    public function activity(Request $request): JsonResponse
    {
        $tenantId = CurrentTenant::id();

        $data = Cache::remember("dashboard:activity:{$tenantId}", 60, function () use ($tenantId) {
            $recentWorkOrders = WorkOrder::where('tenant_id', $tenantId)
                ->with(['equipment:id,code,name', 'createdBy:id,name'])
                ->whereIn('status', ['in_progress', 'completed', 'planned', 'on_hold'])
                ->orderByDesc('updated_at')
                ->limit(6)
                ->get()
                ->map(fn (WorkOrder $wo) => [
                    'id' => $wo->id,
                    'type' => 'work_order',
                    'work_order_number' => $wo->work_order_number,
                    'title' => $wo->title,
                    'status' => $wo->status,
                    'priority' => $wo->priority,
                    'equipment_code' => $wo->equipment?->code,
                    'equipment_name' => $wo->equipment?->name,
                    'created_by_name' => $wo->createdBy?->name,
                    'updated_at' => $wo->updated_at?->toISOString(),
                ]);

            $recentComments = WorkOrderComment::where('tenant_id', $tenantId)
                ->with([
                    'user:id,name',
                    'workOrder:id,work_order_number,title',
                ])
                ->where('is_internal', false)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn (WorkOrderComment $c) => [
                    'id' => $c->id,
                    'type' => 'comment',
                    'body' => mb_strimwidth($c->body, 0, 120, '…'),
                    'user_name' => $c->user?->name,
                    'work_order_number' => $c->workOrder?->work_order_number,
                    'work_order_title' => $c->workOrder?->title,
                    'work_order_id' => $c->work_order_id,
                    'created_at' => $c->created_at->toISOString(),
                ]);

            return [
                'work_orders' => $recentWorkOrders,
                'comments' => $recentComments,
            ];
        });

        return response()->json($data);
    }

    public function novedades(Request $request): JsonResponse
    {
        $tenantId = CurrentTenant::id();

        $data = Cache::remember("dashboard:novedades:{$tenantId}", 120, function () use ($tenantId) {
            $criticalAlerts = Alert::where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->whereIn('severity', ['critical', 'high'])
                ->with('equipment:id,code,name')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn (Alert $a) => [
                    'id' => $a->id,
                    'severity' => $a->severity,
                    'category' => $a->category,
                    'message' => $a->message,
                    'equipment_code' => $a->equipment?->code,
                    'created_at' => $a->created_at->toISOString(),
                ]);

            $upcomingPlans = MaintenancePlan::where('maintenance_plans.tenant_id', $tenantId)
                ->where('maintenance_plans.is_active', true)
                ->join('maintenance_schedules', 'maintenance_plans.id', '=', 'maintenance_schedules.maintenance_plan_id')
                ->whereNotNull('maintenance_schedules.next_due_at')
                ->where('maintenance_schedules.next_due_at', '<=', now()->addDays(14))
                ->with('equipment:id,code,name')
                ->select('maintenance_plans.*', 'maintenance_schedules.next_due_at', 'maintenance_schedules.next_due_meter')
                ->orderBy('maintenance_schedules.next_due_at')
                ->limit(5)
                ->get()
                ->map(fn (MaintenancePlan $p) => [
                    'id' => $p->id,
                    'plan_number' => $p->plan_number,
                    'name' => $p->name,
                    'next_due_at' => $p->next_due_at?->toISOString(),
                    'is_overdue' => $p->next_due_at?->isPast() ?? false,
                    'equipment_code' => $p->equipment?->code,
                    'equipment_name' => $p->equipment?->name,
                ]);

            return [
                'critical_alerts' => $criticalAlerts,
                'upcoming_plans' => $upcomingPlans,
            ];
        });

        return response()->json($data);
    }

    public function images(): JsonResponse
    {
        // Placeholder — ready for the institutional photo upload feature (PX-2).
        return response()->json(['data' => []]);
    }
}
