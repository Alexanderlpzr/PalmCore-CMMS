<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Models\WorkOrderComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FeedController extends Controller
{
    private const int PER_PAGE = 15;

    private const int SOURCE_LIMIT = 50;

    public function index(Request $request): JsonResponse
    {
        abort_if(
            ! $request->user()->tokenCan('home.read') && ! $request->user()->tokenCan('*'),
            403
        );

        $tenantId = CurrentTenant::id();

        $filter = in_array($request->input('filter'), ['all', 'work_order', 'equipment', 'request', 'maintenance'], true)
            ? $request->input('filter')
            : 'all';

        $page = max(1, (int) $request->integer('page', 1));

        $items = Cache::remember("feed:{$tenantId}:{$filter}", 60, fn () => $this->buildItems($tenantId, $filter));

        $total = $items->count();
        $paged = $items->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values();

        return response()->json([
            'data' => [
                'items' => $paged,
                'page' => $page,
                'per_page' => self::PER_PAGE,
                'total' => $total,
                'next_page' => ($page * self::PER_PAGE < $total) ? $page + 1 : null,
            ],
        ]);
    }

    private function buildItems(string $tenantId, string $filter): Collection
    {
        $items = collect();

        if (in_array($filter, ['all', 'work_order'], true)) {
            $items = $items->concat($this->workOrdersCreated($tenantId));
            $items = $items->concat($this->workOrdersCompleted($tenantId));
            $items = $items->concat($this->comments($tenantId));
            $items = $items->concat($this->attachments($tenantId));
        }

        if (in_array($filter, ['all', 'equipment'], true)) {
            $items = $items->concat($this->equipmentCreated($tenantId));
        }

        if (in_array($filter, ['all', 'request'], true)) {
            $items = $items->concat($this->requestsCreated($tenantId));
        }

        if (in_array($filter, ['all', 'maintenance'], true)) {
            $items = $items->concat($this->maintenanceCompleted($tenantId));
        }

        return $items->sortByDesc('occurred_at')->values();
    }

    private function workOrdersCreated(string $tenantId): Collection
    {
        return WorkOrder::where('tenant_id', $tenantId)
            ->with(['createdBy:id,name', 'equipment:id,code,name'])
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (WorkOrder $wo) {
                $actor = $wo->createdBy?->name ?? 'Sistema';
                $subtitle = $wo->equipment
                    ? "{$wo->equipment->code} — {$wo->title}"
                    : $wo->title;

                return [
                    'id' => "wo_created_{$wo->id}",
                    'type' => 'work_order_created',
                    'category' => 'work_order',
                    'icon_type' => 'wrench',
                    'title' => "{$actor} creó {$wo->work_order_number}",
                    'subtitle' => $subtitle,
                    'action_label' => 'Ver OT',
                    'action_route' => 'ops.ordenes.show',
                    'action_id' => $wo->id,
                    'actor' => $wo->createdBy?->name,
                    'occurred_at' => $wo->created_at->toISOString(),
                    'occurred_at_relative' => $wo->created_at->diffForHumans(),
                ];
            });
    }

    private function workOrdersCompleted(string $tenantId): Collection
    {
        return WorkOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['completed', 'cancelled'])
            ->with(['createdBy:id,name', 'equipment:id,code,name'])
            ->orderByDesc('updated_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (WorkOrder $wo) {
                $isCompleted = $wo->status->value === 'completed';
                $actor = $wo->createdBy?->name ?? 'Sistema';

                return [
                    'id' => "wo_closed_{$wo->id}",
                    'type' => $isCompleted ? 'work_order_completed' : 'work_order_cancelled',
                    'category' => 'work_order',
                    'icon_type' => $isCompleted ? 'check' : 'x',
                    'title' => $isCompleted
                        ? "{$actor} completó {$wo->work_order_number}"
                        : "{$wo->work_order_number} fue cancelada",
                    'subtitle' => $wo->equipment
                        ? "{$wo->equipment->code} — {$wo->title}"
                        : $wo->title,
                    'action_label' => 'Ver OT',
                    'action_route' => 'ops.ordenes.show',
                    'action_id' => $wo->id,
                    'actor' => $wo->createdBy?->name,
                    'occurred_at' => $wo->updated_at->toISOString(),
                    'occurred_at_relative' => $wo->updated_at->diffForHumans(),
                ];
            });
    }

    private function comments(string $tenantId): Collection
    {
        return WorkOrderComment::where('tenant_id', $tenantId)
            ->where('is_internal', false)
            ->with(['user:id,name', 'workOrder:id,work_order_number'])
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (WorkOrderComment $c) {
                $user = $c->user?->name ?? 'Alguien';
                $woNumber = $c->workOrder?->work_order_number ?? '—';

                return [
                    'id' => "comment_{$c->id}",
                    'type' => 'comment_added',
                    'category' => 'work_order',
                    'icon_type' => 'comment',
                    'title' => "{$user} comentó en {$woNumber}",
                    'subtitle' => mb_strimwidth($c->body, 0, 80, '…'),
                    'action_label' => 'Ver OT',
                    'action_route' => 'ops.ordenes.show',
                    'action_id' => $c->work_order_id,
                    'actor' => $c->user?->name,
                    'occurred_at' => $c->created_at->toISOString(),
                    'occurred_at_relative' => $c->created_at->diffForHumans(),
                ];
            });
    }

    private function attachments(string $tenantId): Collection
    {
        return WorkOrderAttachment::where('tenant_id', $tenantId)
            ->with(['workOrder:id,work_order_number', 'uploadedBy:id,name'])
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (WorkOrderAttachment $a) {
                $woNumber = $a->workOrder?->work_order_number ?? '—';

                return [
                    'id' => "attachment_{$a->id}",
                    'type' => 'evidence_added',
                    'category' => 'work_order',
                    'icon_type' => 'camera',
                    'title' => "Evidencia adjuntada en {$woNumber}",
                    'subtitle' => $a->caption ?? $a->file_name,
                    'action_label' => 'Ver OT',
                    'action_route' => 'ops.ordenes.show',
                    'action_id' => $a->work_order_id,
                    'actor' => $a->uploadedBy?->name,
                    'occurred_at' => $a->created_at->toISOString(),
                    'occurred_at_relative' => $a->created_at->diffForHumans(),
                ];
            });
    }

    private function equipmentCreated(string $tenantId): Collection
    {
        return Equipment::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (Equipment $e) {
                return [
                    'id' => "equip_{$e->id}",
                    'type' => 'equipment_created',
                    'category' => 'equipment',
                    'icon_type' => 'equipment',
                    'title' => "Nuevo equipo registrado: {$e->code}",
                    'subtitle' => $e->name,
                    'action_label' => 'Ver equipo',
                    'action_route' => 'ops.equipos.show',
                    'action_id' => $e->id,
                    'actor' => null,
                    'occurred_at' => $e->created_at->toISOString(),
                    'occurred_at_relative' => $e->created_at->diffForHumans(),
                ];
            });
    }

    private function requestsCreated(string $tenantId): Collection
    {
        return MaintenanceRequest::where('tenant_id', $tenantId)
            ->with(['createdBy:id,name'])
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (MaintenanceRequest $r) {
                $actor = $r->createdBy?->name ?? 'Alguien';

                return [
                    'id' => "req_{$r->id}",
                    'type' => 'request_created',
                    'category' => 'request',
                    'icon_type' => 'clipboard',
                    'title' => "{$actor} envió una solicitud",
                    'subtitle' => $r->title,
                    'action_label' => 'Ver solicitud',
                    'action_route' => 'ops.solicitudes.show',
                    'action_id' => $r->id,
                    'actor' => $r->createdBy?->name,
                    'occurred_at' => $r->created_at->toISOString(),
                    'occurred_at_relative' => $r->created_at->diffForHumans(),
                ];
            });
    }

    private function maintenanceCompleted(string $tenantId): Collection
    {
        // maintenance_schedules has last_completed_at — use it to surface recently completed cycles.
        // Join to maintenance_plans for the plan name.
        return MaintenancePlan::where('maintenance_plans.tenant_id', $tenantId)
            ->join('maintenance_schedules', 'maintenance_plans.id', '=', 'maintenance_schedules.maintenance_plan_id')
            ->whereNotNull('maintenance_schedules.last_completed_at')
            ->select([
                'maintenance_plans.id',
                'maintenance_plans.name',
                'maintenance_schedules.last_completed_at',
                'maintenance_schedules.id as schedule_id',
            ])
            ->orderByDesc('maintenance_schedules.last_completed_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function ($row) {
                $completedAt = Carbon::parse($row->last_completed_at);

                return [
                    'id' => "maint_{$row->schedule_id}",
                    'type' => 'maintenance_completed',
                    'category' => 'maintenance',
                    'icon_type' => 'tools',
                    'title' => "Mantenimiento completado: {$row->name}",
                    'subtitle' => null,
                    'action_label' => 'Ver plan',
                    'action_route' => 'ops.preventivos',
                    'action_id' => $row->id,
                    'actor' => null,
                    'occurred_at' => $completedAt->toISOString(),
                    'occurred_at_relative' => $completedAt->diffForHumans(),
                ];
            });
    }
}
