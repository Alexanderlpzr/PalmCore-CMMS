<?php

namespace App\Domain\Analytics\Services;

use App\Models\Alert;
use App\Models\Equipment;
use App\Models\EquipmentKpi;
use App\Models\MaintenancePlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cross-tenant aggregates for the Super Admin platform dashboard.
 *
 * Runs WITHOUT a CurrentTenant set, so the tenant global scope is a no-op and
 * the models return platform-wide data. All results are cached for 5 minutes.
 * This service never narrows by tenant — it is reached only behind the
 * super-admin middleware (see PlatformDashboardController).
 */
class PlatformAnalyticsService
{
    private const TTL = 300;

    private const OPEN_WORK_ORDER_STATUSES = ['draft', 'planned', 'in_progress', 'on_hold'];

    /**
     * The eight headline platform metrics.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return Cache::remember('platform:summary', self::TTL, function (): array {
            $startOfMonth = Carbon::now()->startOfMonth();

            return [
                'tenants' => [
                    'total' => Tenant::count(),
                    'active' => Tenant::where('is_active', true)->count(),
                ],
                'users' => [
                    'total' => User::count(),
                    'active' => User::where('is_active', true)->count(),
                ],
                'equipment' => [
                    'total' => Equipment::count(),
                ],
                'open_work_orders' => WorkOrder::whereIn('status', self::OPEN_WORK_ORDER_STATUSES)->count(),
                'preventive_plans' => MaintenancePlan::where('is_active', true)->count(),
                'critical_alerts' => Alert::where('severity', 'critical')->where('status', 'open')->count(),
                'avg_availability' => round((float) (EquipmentKpi::avg('availability_percentage') ?? 0), 2),
                'global_cost_month' => round((float) WorkOrder::whereNotNull('completed_at')
                    ->where('completed_at', '>=', $startOfMonth)
                    ->sum('actual_cost_total'), 2),
            ];
        });
    }

    /**
     * Analytics: rankings, storage usage, and subscription health.
     *
     * @return array<string, mixed>
     */
    public function analytics(): array
    {
        return Cache::remember('platform:analytics', self::TTL, function (): array {
            $tenantNames = Tenant::pluck('name', 'id');

            return [
                'top_by_equipment' => $this->rankByTenant(
                    Equipment::query()->selectRaw('tenant_id, COUNT(*) as aggregate')->groupBy('tenant_id'),
                    $tenantNames,
                ),
                'top_by_work_orders' => $this->rankByTenant(
                    WorkOrder::query()->selectRaw('tenant_id, COUNT(*) as aggregate')->groupBy('tenant_id'),
                    $tenantNames,
                ),
                'top_by_alerts' => $this->rankByTenant(
                    Alert::query()->where('status', 'open')->selectRaw('tenant_id, COUNT(*) as aggregate')->groupBy('tenant_id'),
                    $tenantNames,
                ),
                'storage' => $this->storageUsage($tenantNames),
                'subscriptions' => $this->subscriptions(),
                'expiring_soon' => $this->expiringSoon(),
            ];
        });
    }

    public function forget(): void
    {
        Cache::forget('platform:summary');
        Cache::forget('platform:analytics');
    }

    /**
     * @param  Collection<string, string>  $tenantNames
     * @return list<array{tenant_id: string, name: string, count: int}>
     */
    private function rankByTenant(Builder $query, $tenantNames): array
    {
        return $query->orderByDesc('aggregate')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'tenant_id' => $row->tenant_id,
                'name' => $tenantNames[$row->tenant_id] ?? '—',
                'count' => (int) $row->aggregate,
            ])
            ->all();
    }

    /**
     * Storage occupied per tenant across every file-bearing table (bytes).
     *
     * @param  Collection<string, string>  $tenantNames
     * @return array{total_bytes: int, by_tenant: list<array{tenant_id: string, name: string, bytes: int}>}
     */
    private function storageUsage($tenantNames): array
    {
        $tables = [
            'equipment_documents',
            'equipment_photos',
            'work_order_attachments',
            'maintenance_request_attachments',
            'maintenance_plan_attachments',
        ];

        $byTenant = [];

        foreach ($tables as $table) {
            $rows = DB::table($table)
                ->selectRaw('tenant_id, SUM(file_size) as bytes')
                ->groupBy('tenant_id')
                ->get();

            foreach ($rows as $row) {
                $byTenant[$row->tenant_id] = ($byTenant[$row->tenant_id] ?? 0) + (int) $row->bytes;
            }
        }

        arsort($byTenant);

        $list = [];
        foreach ($byTenant as $tenantId => $bytes) {
            $list[] = [
                'tenant_id' => $tenantId,
                'name' => $tenantNames[$tenantId] ?? '—',
                'bytes' => $bytes,
            ];
        }

        return [
            'total_bytes' => array_sum($byTenant),
            'by_tenant' => array_slice($list, 0, 10),
        ];
    }

    /**
     * @return array{active: int, by_plan: list<array{plan: string, count: int}>}
     */
    private function subscriptions(): array
    {
        $active = Tenant::where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('subscription_expires_at')
                    ->orWhere('subscription_expires_at', '>=', Carbon::today());
            })
            ->count();

        $byPlan = Tenant::selectRaw('subscription_plan, COUNT(*) as aggregate')
            ->groupBy('subscription_plan')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row): array => [
                'plan' => (string) $row->subscription_plan,
                'count' => (int) $row->aggregate,
            ])
            ->all();

        return ['active' => $active, 'by_plan' => $byPlan];
    }

    /**
     * Tenants whose subscription expires within the next 30 days.
     *
     * @return list<array{tenant_id: string, name: string, plan: string, expires_at: string, days_left: int}>
     */
    private function expiringSoon(): array
    {
        $today = Carbon::today();

        return Tenant::whereNotNull('subscription_expires_at')
            ->whereBetween('subscription_expires_at', [$today, $today->copy()->addDays(30)])
            ->orderBy('subscription_expires_at')
            ->get()
            ->map(fn (Tenant $tenant): array => [
                'tenant_id' => $tenant->id,
                'name' => $tenant->name,
                'plan' => (string) $tenant->subscription_plan,
                'expires_at' => $tenant->subscription_expires_at->toDateString(),
                'days_left' => (int) $today->diffInDays($tenant->subscription_expires_at, false),
            ])
            ->all();
    }
}
