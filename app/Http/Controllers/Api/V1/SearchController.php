<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\SparePart;
use App\Models\WorkOrder;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Global cross-resource search powering the command palette (Cmd/Ctrl + K).
 *
 * Results are grouped by resource, capped per group, gated by the same token
 * abilities as the dedicated list endpoints, and tenant-scoped automatically
 * through the BelongsToTenant global scope on every model queried here.
 */
class SearchController extends Controller
{
    private const PER_GROUP = 5;

    private const MIN_LENGTH = 2;

    public function index(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return response()->json(['query' => $term, 'groups' => []]);
        }

        $user = $request->user();
        $can = fn (string $ability): bool => $user->tokenCan($ability) || $user->tokenCan('*');
        $like = '%'.$term.'%';
        $groups = [];

        if ($can('equipment.read')) {
            $groups[] = $this->group('equipment', 'Equipos',
                Equipment::query()
                    ->where($this->ilike($like, ['code', 'name', 'serial_number']))
                    ->orderBy('code')
                    ->limit(self::PER_GROUP)
                    ->get(['id', 'code', 'name', 'status'])
                    ->map(fn (Equipment $e): array => [
                        'id' => $e->id,
                        'title' => $e->name,
                        'subtitle' => $e->code,
                        'status' => $e->status?->value,
                    ]),
            );
        }

        if ($can('work-orders.read')) {
            $groups[] = $this->group('work_orders', 'Órdenes de trabajo',
                WorkOrder::query()
                    ->where($this->ilike($like, ['work_order_number', 'title']))
                    ->orderByDesc('created_at')
                    ->limit(self::PER_GROUP)
                    ->get(['id', 'work_order_number', 'title', 'status'])
                    ->map(fn (WorkOrder $w): array => [
                        'id' => $w->id,
                        'title' => $w->title,
                        'subtitle' => $w->work_order_number,
                        'status' => $w->status?->value,
                    ]),
            );
        }

        if ($can('maintenance-requests.read')) {
            $groups[] = $this->group('maintenance_requests', 'Solicitudes',
                MaintenanceRequest::query()
                    ->where($this->ilike($like, ['request_number', 'title']))
                    ->orderByDesc('created_at')
                    ->limit(self::PER_GROUP)
                    ->get(['id', 'request_number', 'title', 'status'])
                    ->map(fn (MaintenanceRequest $m): array => [
                        'id' => $m->id,
                        'title' => $m->title,
                        'subtitle' => $m->request_number,
                        'status' => $m->status?->value,
                    ]),
            );
        }

        if ($can('inventory.read')) {
            $groups[] = $this->group('spare_parts', 'Repuestos',
                SparePart::query()
                    ->where($this->ilike($like, ['code', 'name']))
                    ->orderBy('code')
                    ->limit(self::PER_GROUP)
                    ->get(['id', 'code', 'name'])
                    ->map(fn (SparePart $p): array => [
                        'id' => $p->id,
                        'title' => $p->name,
                        'subtitle' => $p->code,
                    ]),
            );
        }

        if ($can('maintenance-plans.read')) {
            $groups[] = $this->group('maintenance_plans', 'Preventivos',
                MaintenancePlan::query()
                    ->where($this->ilike($like, ['plan_number', 'name']))
                    ->orderBy('plan_number')
                    ->limit(self::PER_GROUP)
                    ->get(['id', 'plan_number', 'name'])
                    ->map(fn (MaintenancePlan $p): array => [
                        'id' => $p->id,
                        'title' => $p->name,
                        'subtitle' => $p->plan_number,
                    ]),
            );
        }

        $groups = array_values(array_filter($groups, fn (array $g): bool => count($g['items']) > 0));

        return response()->json(['query' => $term, 'groups' => $groups]);
    }

    /**
     * Build a grouped OR-ILIKE constraint across the given columns, kept inside
     * a nested closure so it stays ANDed with the tenant global scope.
     *
     * @param  array<int, string>  $columns
     */
    private function ilike(string $like, array $columns): \Closure
    {
        return function (Builder $query) use ($like, $columns): void {
            foreach ($columns as $i => $column) {
                $i === 0
                    ? $query->where($column, 'ILIKE', $like)
                    : $query->orWhere($column, 'ILIKE', $like);
            }
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{type: string, label: string, items: Collection<int, array<string, mixed>>}
     */
    private function group(string $type, string $label, $items): array
    {
        return ['type' => $type, 'label' => $label, 'items' => $items];
    }
}
