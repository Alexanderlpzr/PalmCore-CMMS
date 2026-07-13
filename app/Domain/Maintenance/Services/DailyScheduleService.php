<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Models\Plant;
use App\Models\WorkOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * El programa del día — lo que el planificador reparte en la mañana.
 *
 * This is the Excel Fronda has to replace, so it has to answer the planner's
 * actual question — «¿quién hace qué hoy?» — and not merely list open OTs. Three
 * things belong on that sheet:
 *
 *  - Lo programado para hoy: OTs planificadas para esta fecha.
 *  - Lo que viene corriendo: OTs ya en ejecución. The técnico is still on them and
 *    they eat his day whether the paper mentions them or not.
 *  - Lo atrasado: OTs planificadas para *antes* de hoy que nadie cerró. Leaving
 *    them off the sheet is exactly how a CMMS ends up living next to the Excel:
 *    the planner keeps a private list of what the system forgot.
 *
 * Un borrador no es un programa. Drafts stay out — they have not been planned yet.
 */
class DailyScheduleService
{
    private const SCHEDULED_STATUSES = [
        WorkOrderStatus::Planned,
        WorkOrderStatus::InProgress,
        WorkOrderStatus::OnHold,
    ];

    /**
     * @return array{
     *     day: CarbonInterface,
     *     plant: ?Plant,
     *     groups: array<int, array{technician: ?string, is_contractor: bool, work_orders: Collection<int, WorkOrder>}>,
     *     work_orders: Collection<int, WorkOrder>,
     *     planned_hours: ?float,
     *     stopped_count: int,
     *     overdue_count: int,
     *     unassigned_count: int,
     * }
     */
    public function forDay(string $tenantId, CarbonInterface $day, ?string $plantId = null): array
    {
        $day = Carbon::parse($day)->startOfDay();

        $workOrders = $this->query($tenantId, $day, $plantId);

        $plannedHours = $workOrders
            ->map(fn (WorkOrder $workOrder): ?float => $workOrder->plannedHours())
            ->filter(fn (?float $hours): bool => $hours !== null)
            ->sum();

        return [
            'day' => $day,
            'plant' => $plantId !== null
                ? Plant::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($plantId)
                : null,
            'groups' => $this->groupByTechnician($workOrders),
            'work_orders' => $workOrders,
            // Nobody typed a duration → no total. A programme that claims «0 h» for
            // eight OTs is worse than one that admits it does not know.
            'planned_hours' => $plannedHours > 0 ? round((float) $plannedHours, 2) : null,
            'stopped_count' => $workOrders->where('equipment_stopped', true)->count(),
            'overdue_count' => $workOrders->filter(fn (WorkOrder $workOrder): bool => $this->isOverdue($workOrder, $day))->count(),
            // Una OT en manos de un contratista tiene responsable: no está sin asignar.
            'unassigned_count' => $workOrders
                ->filter(fn (WorkOrder $workOrder): bool => $workOrder->technicians->isEmpty()
                    && $workOrder->contractors->isEmpty())
                ->count(),
        ];
    }

    /** Una OT planificada para antes de hoy que sigue viva. */
    public function isOverdue(WorkOrder $workOrder, CarbonInterface $day): bool
    {
        return $workOrder->planned_start_at !== null
            && $workOrder->planned_start_at->lt(Carbon::parse($day)->startOfDay());
    }

    /** @return Collection<int, WorkOrder> */
    private function query(string $tenantId, CarbonInterface $day, ?string $plantId): Collection
    {
        return WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->when($plantId !== null, fn ($query) => $query->where('plant_id', $plantId))
            ->whereIn('status', array_map(
                fn (WorkOrderStatus $status): string => $status->value,
                self::SCHEDULED_STATUSES,
            ))
            ->where(fn ($query) => $query
                // Programada para hoy — o para antes, y todavía abierta.
                ->whereDate('planned_start_at', '<=', $day->toDateString())
                // Ya arrancó: ocupa al técnico aunque nadie la planificara.
                ->orWhereIn('status', [WorkOrderStatus::InProgress->value, WorkOrderStatus::OnHold->value]))
            ->with(['equipment.area', 'technicians.user', 'contractors.contractor', 'parts'])
            ->orderByRaw("CASE priority
                WHEN 'p1_critical' THEN 1
                WHEN 'p2_high' THEN 2
                WHEN 'p3_medium' THEN 3
                WHEN 'p4_low' THEN 4
                WHEN 'p5_planned' THEN 5
                ELSE 6 END")
            ->orderBy('planned_start_at')
            ->get();
    }

    /**
     * Una hoja por responsable. Las que no tienen dueño van primero: son la
     * decisión que el planificador tiene que tomar antes de repartir el papel.
     *
     * Un responsable puede no ser un empleado. En la programación real del 15/06
     * el responsable de un trabajo es «MONTAJES INDUSTRIALES HF»: si el contratista
     * no puede encabezar una hoja, el programa impreso desde Fronda sale con filas
     * de menos y el planificador vuelve a su Excel.
     *
     * @param  Collection<int, WorkOrder>  $workOrders
     * @return array<int, array{technician: ?string, is_contractor: bool, work_orders: Collection<int, WorkOrder>}>
     */
    private function groupByTechnician(Collection $workOrders): array
    {
        $groups = [];

        $hasOwner = fn (WorkOrder $workOrder): bool => $workOrder->technicians->isNotEmpty()
            || $workOrder->contractors->isNotEmpty();

        $unassigned = $workOrders->reject($hasOwner);

        if ($unassigned->isNotEmpty()) {
            $groups[] = ['technician' => null, 'is_contractor' => false, 'work_orders' => $unassigned];
        }

        // Una OT con dos responsables aparece en la hoja de los dos: los dos van a ir.
        $byOwner = [];

        foreach ($workOrders->filter($hasOwner) as $workOrder) {
            foreach ($workOrder->technicians as $technician) {
                $name = $technician->user?->name ?? 'Técnico sin nombre';
                $byOwner[$name]['is_contractor'] = false;
                $byOwner[$name]['work_orders'][] = $workOrder;
            }

            foreach ($workOrder->contractors as $assignment) {
                $name = $assignment->contractor?->name ?? 'Contratista sin nombre';
                $byOwner[$name]['is_contractor'] = true;
                $byOwner[$name]['work_orders'][] = $workOrder;
            }
        }

        ksort($byOwner);

        foreach ($byOwner as $name => $group) {
            $groups[] = [
                'technician' => $name,
                'is_contractor' => $group['is_contractor'],
                'work_orders' => new Collection($group['work_orders']),
            ];
        }

        return $groups;
    }
}
