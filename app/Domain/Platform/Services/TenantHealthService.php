<?php

namespace App\Domain\Platform\Services;

use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertStatus;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Models\Alert;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;

/**
 * ¿Esta empresa está viva, o es un zombi?
 *
 * Un CMMS no muere con un error: muere el mes en que nadie vuelve a entrar. Una
 * empresa que paga y no usa el sistema es un cliente que se va a ir, y el aviso llega
 * meses antes si alguien está mirando. Estos números son ese aviso.
 *
 * La actividad se mide por el último ingreso real de un usuario, no por `updated_at`
 * de una fila cualquiera: un job automático que toca la base no significa que alguien
 * de la planta esté usando Fronda.
 */
class TenantHealthService
{
    /** Días sin que nadie entre para considerar la empresa inactiva. */
    private const DORMANT_DAYS = 14;

    /**
     * @return list<array{
     *     tenant: Tenant,
     *     users: int,
     *     active_users: int,
     *     equipment: int,
     *     open_work_orders: int,
     *     overdue_work_orders: int,
     *     critical_alerts: int,
     *     last_activity_at: ?Carbon,
     *     days_since_activity: ?int,
     *     is_dormant: bool,
     * }>
     */
    public function overview(): array
    {
        $tenants = Tenant::withoutGlobalScopes()->orderBy('name')->get();

        return $tenants
            ->map(fn (Tenant $tenant): array => $this->forTenant($tenant))
            ->all();
    }

    /** @return array<string, mixed> */
    public function forTenant(Tenant $tenant): array
    {
        $users = User::whereHas('tenants', fn ($query) => $query->where('tenants.id', $tenant->id));

        $lastActivityAt = (clone $users)->max('last_login_at');
        $lastActivityAt = $lastActivityAt !== null ? Carbon::parse($lastActivityAt) : null;

        $daysSince = $lastActivityAt?->diffInDays(now());
        $daysSince = $daysSince !== null ? (int) floor($daysSince) : null;

        return [
            'tenant' => $tenant,
            'users' => (clone $users)->count(),
            // «Activo» es quien entró en las últimas dos semanas, no quien tiene cuenta.
            'active_users' => (clone $users)
                ->where('last_login_at', '>=', now()->subDays(self::DORMANT_DAYS))
                ->count(),
            'equipment' => Equipment::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->count(),
            'open_work_orders' => WorkOrder::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereNotIn('status', [
                    WorkOrderStatus::Completed->value,
                    WorkOrderStatus::Verified->value,
                    WorkOrderStatus::Closed->value,
                    WorkOrderStatus::Cancelled->value,
                ])
                ->count(),
            // La OT que se pasó de su fecha planificada y sigue abierta: la deuda real.
            'overdue_work_orders' => WorkOrder::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereNotNull('planned_end_at')
                ->where('planned_end_at', '<', now())
                ->whereNotIn('status', [
                    WorkOrderStatus::Completed->value,
                    WorkOrderStatus::Verified->value,
                    WorkOrderStatus::Closed->value,
                    WorkOrderStatus::Cancelled->value,
                ])
                ->count(),
            'critical_alerts' => Alert::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('status', AlertStatus::Open->value)
                ->where('severity', AlertSeverity::Critical->value)
                ->whereNull('deleted_at')
                ->count(),
            'last_activity_at' => $lastActivityAt,
            'days_since_activity' => $daysSince,
            // Nunca entró nadie, o hace más de dos semanas que no entra nadie.
            'is_dormant' => $lastActivityAt === null || $daysSince >= self::DORMANT_DAYS,
        ];
    }
}
