<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Announcement;
use App\Models\CarouselSlide;
use App\Models\InstitutionalContent;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function carousel(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('home.read') && ! $request->user()->tokenCan('*'), 403);

        $tenantId = CurrentTenant::id();

        $slides = Cache::remember("home:carousel:{$tenantId}", 3600, function () {
            return CarouselSlide::visible()
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->get()
                ->map(fn (CarouselSlide $s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'subtitle' => $s->subtitle,
                    'description' => $s->description,
                    'image_url' => $s->imageUrl(),
                    'button_label' => $s->button_label,
                    'button_url' => $s->button_url,
                ]);
        });

        return response()->json(['data' => $slides]);
    }

    public function notices(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('home.read') && ! $request->user()->tokenCan('*'), 403);

        $tenantId = CurrentTenant::id();

        $data = Cache::remember("home:notices:{$tenantId}", 300, function () use ($tenantId) {
            $overdueWOs = WorkOrder::where('tenant_id', $tenantId)
                ->whereIn('status', ['draft', 'planned', 'in_progress', 'on_hold'])
                ->whereNotNull('planned_end_at')
                ->where('planned_end_at', '<', now())
                ->count();

            $pendingRequests = MaintenanceRequest::where('tenant_id', $tenantId)
                ->whereIn('status', ['submitted', 'under_review'])
                ->count();

            $upcomingPreventivos = MaintenancePlan::where('maintenance_plans.tenant_id', $tenantId)
                ->where('maintenance_plans.is_active', true)
                ->join('maintenance_schedules', 'maintenance_plans.id', '=', 'maintenance_schedules.maintenance_plan_id')
                ->whereNotNull('maintenance_schedules.next_due_at')
                ->where('maintenance_schedules.next_due_at', '<=', now()->addDays(7))
                ->count();

            $stoppedEquipment = WorkOrder::where('tenant_id', $tenantId)
                ->where('equipment_stopped', true)
                ->whereIn('status', ['planned', 'in_progress', 'on_hold'])
                ->distinct('equipment_id')
                ->count('equipment_id');

            return [
                [
                    'type' => 'overdue_wo',
                    'count' => $overdueWOs,
                    'label' => 'OTs vencidas',
                    'route' => 'ops.ordenes',
                    'color' => 'red',
                    'visible' => $overdueWOs > 0,
                ],
                [
                    'type' => 'pending_requests',
                    'count' => $pendingRequests,
                    'label' => 'Solicitudes pendientes',
                    'route' => 'ops.solicitudes',
                    'color' => 'amber',
                    'visible' => $pendingRequests > 0,
                ],
                [
                    'type' => 'upcoming_preventivos',
                    'count' => $upcomingPreventivos,
                    'label' => 'Preventivos próximos (7 días)',
                    'route' => 'ops.preventivos',
                    'color' => 'blue',
                    'visible' => $upcomingPreventivos > 0,
                ],
                [
                    'type' => 'stopped_equipment',
                    'count' => $stoppedEquipment,
                    'label' => 'Equipos detenidos',
                    'route' => 'ops.equipos',
                    'color' => 'orange',
                    'visible' => $stoppedEquipment > 0,
                ],
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function announcements(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('home.read') && ! $request->user()->tokenCan('*'), 403);

        $tenantId = CurrentTenant::id();

        $data = Cache::remember("home:announcements:{$tenantId}", 1800, function () {
            return Announcement::published()
                ->with('author:id,name')
                ->orderByDesc('is_pinned')
                ->orderBy('sort_order')
                ->orderByDesc('published_at')
                ->limit(20)
                ->get()
                ->map(fn (Announcement $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'subtitle' => $a->subtitle,
                    'body' => $a->body,
                    'category' => $a->category->value,
                    'category_label' => $a->category->label(),
                    'category_color' => $a->category->color(),
                    'image_url' => $a->imageUrl(),
                    'button_label' => $a->button_label,
                    'button_url' => $a->button_url,
                    'is_pinned' => $a->is_pinned,
                    'author_name' => $a->author?->name,
                    'published_at' => $a->published_at?->toISOString(),
                ]);
        });

        return response()->json(['data' => $data]);
    }

    public function content(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('home.read') && ! $request->user()->tokenCan('*'), 403);

        $tenantId = CurrentTenant::id();

        $items = Cache::tags(['institutional-content'])->remember("home:content:{$tenantId}", 300, function () use ($tenantId) {
            return InstitutionalContent::visibleForTenant($tenantId)
                ->orderBy('display_order')
                ->get()
                ->map(fn (InstitutionalContent $item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'subtitle' => $item->subtitle,
                    'description' => $item->description,
                    'image_url' => $item->imageUrl(),
                    'button_text' => $item->button_text,
                    'button_url' => $item->button_url,
                    'type' => $item->type->value,
                    'display_order' => $item->display_order,
                ]);
        });

        return response()->json(['data' => $items]);
    }

    public function activity(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('home.read') && ! $request->user()->tokenCan('*'), 403);

        $tenantId = CurrentTenant::id();

        $data = Cache::remember("home:activity:{$tenantId}", 120, function () use ($tenantId) {
            $wos = WorkOrder::where('tenant_id', $tenantId)
                ->with(['equipment:id,code,name', 'createdBy:id,name'])
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get()
                ->map(fn (WorkOrder $wo) => [
                    'id' => $wo->id,
                    'type' => 'work_order',
                    'title' => $wo->title,
                    'subtitle' => $wo->work_order_number.($wo->equipment ? ' · '.$wo->equipment->code : ''),
                    'status' => $wo->status,
                    'user_name' => $wo->createdBy?->name,
                    'route' => 'ops.ordenes.show',
                    'updated_at' => $wo->updated_at?->toISOString(),
                ]);

            $comments = WorkOrderComment::where('tenant_id', $tenantId)
                ->with(['user:id,name', 'workOrder:id,work_order_number'])
                ->where('is_internal', false)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn (WorkOrderComment $c) => [
                    'id' => $c->work_order_id,
                    'type' => 'comment',
                    'title' => mb_strimwidth($c->body, 0, 90, '…'),
                    'subtitle' => $c->workOrder?->work_order_number ?? '—',
                    'user_name' => $c->user?->name,
                    'route' => 'ops.ordenes.show',
                    'updated_at' => $c->created_at->toISOString(),
                ]);

            return collect($wos)->concat($comments)
                ->sortByDesc('updated_at')
                ->values()
                ->take(12);
        });

        return response()->json(['data' => $data]);
    }
}
