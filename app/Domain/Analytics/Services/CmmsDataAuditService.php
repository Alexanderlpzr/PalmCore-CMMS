<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\DTOs\AuditFinding;
use App\Domain\Analytics\Enums\AuditSeverity;
use App\Domain\Maintenance\Services\StaleMeterReadingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría de integridad de datos del CMMS.
 *
 * No mide el desempeño de la planta (eso es el dashboard), sino la salud de los
 * datos que ese desempeño necesita para ser cierto: un equipo crítico sin plan,
 * un plan vencido que no generó su OT, un horómetro que dejó de hablar. Son los
 * huecos por los que un programa de mantenimiento se vacía sin que nadie lo note.
 *
 * Todo con query builder y `tenant_id` explícito a propósito: una auditoría no
 * puede depender de que un global scope esté activo para no mentir sobre lo que
 * hay en la base.
 */
class CmmsDataAuditService
{
    /** Cuántos días sin movimiento hacen que una OT abierta cuente como atascada. */
    private const STUCK_WORK_ORDER_DAYS = 30;

    /** Tope de ejemplos que se listan por hallazgo — el conteo total es el que importa. */
    private const SAMPLE_LIMIT = 12;

    public function __construct(private readonly StaleMeterReadingService $staleMeters) {}

    /**
     * @return list<AuditFinding> solo los hallazgos con al menos un caso, lo crítico primero
     */
    public function run(string $tenantId): array
    {
        $findings = array_filter([
            $this->criticalEquipmentWithoutPlan($tenantId),
            $this->overduePlansWithoutOpenWorkOrder($tenantId),
            $this->activeMeterPlansNeverActivated($tenantId),
            $this->staleMeters($tenantId),
            $this->stuckWorkOrders($tenantId),
            $this->componentsPastUsefulLife($tenantId),
            $this->preventiveWorkOrdersWithoutPlan($tenantId),
        ]);

        usort($findings, fn (AuditFinding $a, AuditFinding $b): int => $a->severity->weight() <=> $b->severity->weight());

        return array_values($findings);
    }

    // ── Chequeos ──────────────────────────────────────────────────────────────

    private function criticalEquipmentWithoutPlan(string $tenantId): ?AuditFinding
    {
        $rows = DB::table('equipment as e')
            ->leftJoin('maintenance_plans as mp', function ($join): void {
                $join->on('mp.equipment_id', '=', 'e.id')->whereNull('mp.deleted_at');
            })
            ->where('e.tenant_id', $tenantId)
            ->where('e.is_active', true)
            ->whereIn('e.criticality', ['critical', 'high'])
            ->whereNull('e.deleted_at')
            ->whereNull('mp.id')
            ->orderBy('e.code')
            ->get(['e.code', 'e.name']);

        return $this->finding(
            'critical_equipment_without_plan',
            AuditSeverity::Critical,
            'Equipos críticos sin plan de mantenimiento',
            'Equipos activos de criticidad alta o crítica que no tienen ningún plan preventivo. Un activo crítico sin plan solo se atiende cuando ya falló.',
            $rows->map(fn ($r): string => "{$r->code} — {$r->name}"),
        );
    }

    private function overduePlansWithoutOpenWorkOrder(string $tenantId): ?AuditFinding
    {
        $rows = DB::table('maintenance_plans as mp')
            ->join('maintenance_schedules as ms', 'ms.maintenance_plan_id', '=', 'mp.id')
            ->join('equipment as e', 'mp.equipment_id', '=', 'e.id')
            ->where('mp.tenant_id', $tenantId)
            ->where('mp.is_active', true)
            ->whereNull('mp.deleted_at')
            ->where(function ($query): void {
                $query->where('ms.next_due_at', '<', now())
                    ->orWhereRaw('ms.next_due_meter <= COALESCE(e.accumulated_meter_reading, 0)');
            })
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('work_orders as wo')
                    ->whereColumn('wo.maintenance_plan_id', 'mp.id')
                    ->whereNull('wo.deleted_at')
                    ->whereIn('wo.status', ['draft', 'planned', 'in_progress', 'on_hold']);
            })
            ->orderBy('mp.plan_number')
            ->get(['mp.plan_number', 'mp.name']);

        return $this->finding(
            'overdue_plans_without_work_order',
            AuditSeverity::Critical,
            'Planes vencidos sin orden de trabajo',
            'Planes activos que ya pasaron su fecha u horómetro de vencimiento pero no tienen ninguna OT abierta. La OT debió generarse sola: revisar que el equipo tenga horómetro al día o que el plan no esté trabado.',
            $rows->map(fn ($r): string => "{$r->plan_number} — {$r->name}"),
        );
    }

    private function activeMeterPlansNeverActivated(string $tenantId): ?AuditFinding
    {
        $rows = DB::table('maintenance_plans as mp')
            ->join('maintenance_schedules as ms', 'ms.maintenance_plan_id', '=', 'mp.id')
            ->where('mp.tenant_id', $tenantId)
            ->where('mp.is_active', true)
            ->whereNull('mp.deleted_at')
            ->whereIn('mp.trigger_source', ['meter', 'hybrid'])
            ->whereNull('ms.next_due_meter')
            ->orderBy('mp.plan_number')
            ->get(['mp.plan_number', 'mp.name']);

        return $this->finding(
            'meter_plans_never_activated',
            AuditSeverity::Warning,
            'Planes por horómetro sin vencimiento definido',
            'Planes por horómetro marcados como activos pero sin un horómetro objetivo. Nunca generan OT: les falta activarse con su primer vencimiento.',
            $rows->map(fn ($r): string => "{$r->plan_number} — {$r->name}"),
        );
    }

    private function staleMeters(string $tenantId): ?AuditFinding
    {
        $stale = collect($this->staleMeters->detect($tenantId));

        return $this->finding(
            'stale_meters',
            AuditSeverity::Warning,
            'Horómetros sin lectura reciente',
            'Equipos con planes por horómetro cuyo horómetro no se lee hace días. Sin lectura, el vencimiento por horas no se puede calcular y el preventivo queda ciego.',
            $stale->map(fn (array $row): string => "{$row['equipment']->code} ({$row['days_without_reading']} días)"),
        );
    }

    private function stuckWorkOrders(string $tenantId): ?AuditFinding
    {
        $rows = DB::table('work_orders')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['in_progress', 'on_hold'])
            ->where('updated_at', '<', now()->subDays(self::STUCK_WORK_ORDER_DAYS))
            ->orderBy('updated_at')
            ->get(['work_order_number', 'title']);

        return $this->finding(
            'stuck_work_orders',
            AuditSeverity::Warning,
            'Órdenes de trabajo atascadas',
            'OTs en ejecución o en espera sin ningún movimiento en más de '.self::STUCK_WORK_ORDER_DAYS.' días. O ya se hicieron y nadie las cerró, o se abandonaron.',
            $rows->map(fn ($r): string => "{$r->work_order_number} — {$r->title}"),
        );
    }

    private function componentsPastUsefulLife(string $tenantId): ?AuditFinding
    {
        $rows = DB::table('equipment_components')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->whereNotNull('useful_life_hours')
            ->whereNotNull('worked_hours')
            ->whereColumn('worked_hours', '>', 'useful_life_hours')
            ->orderByDesc('worked_hours')
            ->get(['name', 'code']);

        return $this->finding(
            'components_past_useful_life',
            AuditSeverity::Warning,
            'Componentes que superaron su vida útil',
            'Piezas todavía marcadas como operativas cuyas horas trabajadas ya pasaron su vida útil estimada. Candidatas a reemplazo antes de que fallen.',
            $rows->map(fn ($r): string => $r->code ? "{$r->code} — {$r->name}" : $r->name),
        );
    }

    private function preventiveWorkOrdersWithoutPlan(string $tenantId): ?AuditFinding
    {
        $rows = DB::table('work_orders')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('work_order_type', 'preventive')
            ->whereNull('maintenance_plan_id')
            ->orderByDesc('created_at')
            ->get(['work_order_number', 'title']);

        return $this->finding(
            'preventive_work_orders_without_plan',
            AuditSeverity::Info,
            'Preventivos sin plan de origen',
            'OTs de tipo preventivo que no están ligadas a ningún plan. No cierran el ciclo del programa: al completarse no adelantan ningún vencimiento.',
            $rows->map(fn ($r): string => "{$r->work_order_number} — {$r->title}"),
        );
    }

    // ── Interno ─────────────────────────────────────────────────────────────────

    /**
     * @param  Collection<int, string>  $labels
     */
    private function finding(
        string $key,
        AuditSeverity $severity,
        string $title,
        string $description,
        Collection $labels,
    ): ?AuditFinding {
        $count = $labels->count();

        if ($count === 0) {
            return null;
        }

        return new AuditFinding(
            key: $key,
            severity: $severity,
            title: $title,
            description: $description,
            count: $count,
            sample: $labels->take(self::SAMPLE_LIMIT)->values()->all(),
        );
    }
}
