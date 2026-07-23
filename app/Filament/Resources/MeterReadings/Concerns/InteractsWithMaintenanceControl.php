<?php

namespace App\Filament\Resources\MeterReadings\Concerns;

use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Domain\Maintenance\Services\MaintenancePlanService;
use App\Domain\Maintenance\Services\PreventiveWorkOrderGenerator;
use App\Models\MaintenancePlan;
use App\Models\WorkOrder;
use Filament\Notifications\Notification;

/**
 * El tablero de Control de Mantenimiento por horómetros, calcado del Excel que
 * lleva el ingeniero: una fila por tarea de mantenimiento, agrupadas por equipo,
 * con la frecuencia, el horómetro del último mantenimiento, el actual, el próximo,
 * las horas y días que faltan, y el semáforo verde/ámbar/rojo.
 *
 * No inventa un motor nuevo: cada fila es un `MaintenancePlan` por horómetro que ya
 * sabemos programar. La tabla solo lo presenta y deja editar la frecuencia, el
 * último mtto y el umbral de aviso en la misma celda, más registrar un mantenimiento
 * hecho y crear la OT — todo apoyado en los servicios que ya existen.
 *
 * @property string $tab la pestaña activa — la declara la página
 */
trait InteractsWithMaintenanceControl
{
    /** Valores tecleados pendientes de guardar: controlDraft[planId][field]. */
    public array $controlDraft = [];

    // ── Datos ─────────────────────────────────────────────────────────────────

    /**
     * Grupos equipo → filas de tareas, calculados sobre los planes por horómetro
     * activos. El consumo por día se cachea por equipo para no consultar las
     * lecturas una vez por cada tarea del mismo equipo.
     *
     * @return list<array<string, mixed>>
     */
    public function controlGroups(): array
    {
        $meters = app(EquipmentMeterReadingService::class);

        $plans = MaintenancePlan::query()
            ->where('is_active', true)
            ->whereIn('trigger_source', [
                MaintenanceTriggerSource::Meter->value,
                MaintenanceTriggerSource::Hybrid->value,
            ])
            ->with(['equipment', 'schedule', 'equipmentComponent'])
            ->get()
            ->filter(fn (MaintenancePlan $plan): bool => $plan->equipment !== null
                && ! in_array($plan->equipment->status, [EquipmentStatus::Retired, EquipmentStatus::Disposed], true))
            ->sort(function (MaintenancePlan $a, MaintenancePlan $b): int {
                // Por equipo; dentro de cada equipo el plan del equipo entero primero
                // (la fila «en negrilla» del Excel), luego los de pieza, por nombre.
                return [$a->equipment->code, $a->equipment_component_id !== null ? 1 : 0, $a->name]
                    <=> [$b->equipment->code, $b->equipment_component_id !== null ? 1 : 0, $b->name];
            });

        $openPlanIds = WorkOrder::withoutGlobalScopes()
            ->whereIn('status', [
                WorkOrderStatus::Draft->value,
                WorkOrderStatus::Planned->value,
                WorkOrderStatus::InProgress->value,
                WorkOrderStatus::OnHold->value,
            ])
            ->whereNotNull('maintenance_plan_id')
            ->pluck('maintenance_plan_id')
            ->flip();

        $paceCache = [];
        $groups = [];

        foreach ($plans as $plan) {
            $equipment = $plan->equipment;
            $schedule = $plan->schedule;

            $current = round($meters->accumulatedReading($equipment), 1);
            $next = $schedule?->next_due_meter;
            $lead = (int) ($plan->meter_lead_hours ?? PreventiveWorkOrderGenerator::DEFAULT_METER_LEAD_HOURS);

            // Firmado a propósito: el Excel muestra en rojo cuánto se pasó (−624 h),
            // no lo esconde en cero como hace metersRemaining para el generador.
            $remaining = $next !== null ? round((float) $next - $current, 1) : null;

            $pace = $paceCache[$equipment->id] ??= ($meters->consumptionPerDay($equipment) ?? 0.0);
            $days = ($remaining !== null && $pace > 0) ? (int) ceil($remaining / $pace) : null;

            $hasOpenOt = isset($openPlanIds[$plan->id]);
            $color = $meters->remainingColor($plan);

            // Las celdas editables leen su valor del borrador. Se siembra con lo que
            // hay hoy solo si no está ya presente, para no pisar lo que el usuario
            // esté tecleando. Tras guardar, saveControlCell lo borra y aquí se vuelve
            // a sembrar con el dato fresco. (Livewire persiste lo que se muta al render.)
            $this->seedControlCell($plan->id, 'meter_interval', $plan->meter_interval);
            $this->seedControlCell($plan->id, 'last_completed_meter', $schedule?->last_completed_meter);
            $this->seedControlCell($plan->id, 'meter_lead_hours', $plan->meter_lead_hours);

            $groups[$equipment->id]['equipment'] ??= [
                'id' => $equipment->id,
                'code' => $equipment->code,
                'name' => $equipment->name,
                'current' => $current,
            ];

            $groups[$equipment->id]['rows'][] = [
                'plan_id' => $plan->id,
                'task' => $plan->name,
                'is_equipment_level' => $plan->equipment_component_id === null,
                'component' => $plan->equipmentComponent?->name,
                'frequency' => $plan->meter_interval,
                'last_meter' => $schedule?->last_completed_meter,
                'current' => $current,
                'next' => $next !== null ? round((float) $next, 1) : null,
                'remaining' => $remaining,
                'days' => $days,
                'lead' => $lead,
                'color' => $color,
                'has_open_ot' => $hasOpenOt,
                // En ámbar o rojo ya se puede armar la OT; salvo que ya haya una abierta.
                'can_create_ot' => in_array($color, ['warning', 'danger'], true) && ! $hasOpenOt,
            ];
        }

        return array_values($groups);
    }

    // ── Edición en la celda ───────────────────────────────────────────────────

    /**
     * Guarda una celda editable de la tabla de Control. Solo tres campos son
     * editables; cualquier otro se ignora. Cambiar la frecuencia o el último mtto
     * recalcula el próximo mantenimiento (próximo = último + frecuencia), que es la
     * cuenta que el ingeniero lleva en el Excel.
     */
    public function saveControlCell(string $planId, string $field): void
    {
        if (! $this->canWriteControl()) {
            return;
        }

        if (! in_array($field, ['meter_interval', 'last_completed_meter', 'meter_lead_hours'], true)) {
            return;
        }

        $plan = MaintenancePlan::with('schedule')->find($planId);

        if ($plan === null) {
            return;
        }

        $raw = $this->controlDraft[$planId][$field] ?? null;
        $value = ($raw === '' || $raw === null) ? null : (float) $raw;

        if ($field === 'meter_interval') {
            if ($value === null || $value <= 0) {
                Notification::make()->title('La frecuencia debe ser mayor que cero.')->danger()->send();

                return;
            }

            $plan->update(['meter_interval' => (int) $value]);
        } elseif ($field === 'meter_lead_hours') {
            $plan->update(['meter_lead_hours' => $value === null ? null : (int) $value]);
        } elseif ($field === 'last_completed_meter') {
            $plan->schedule?->update(['last_completed_meter' => $value]);
        }

        $this->recomputeNextDue($plan->fresh('schedule'));

        unset($this->controlDraft[$planId][$field]);

        Notification::make()->title('Guardado')->success()->send();
    }

    /**
     * Registra que el mantenimiento se hizo ahora, al horómetro actual del equipo:
     * fija el último mtto, adelanta el próximo un intervalo y suma una ejecución,
     * sin fabricar una OT. Es el «se hizo» de la fila.
     */
    public function registrarMantenimiento(string $planId): void
    {
        if (! $this->canWriteControl()) {
            return;
        }

        $plan = MaintenancePlan::with(['equipment', 'schedule'])->find($planId);

        if ($plan?->equipment === null) {
            return;
        }

        $meter = app(EquipmentMeterReadingService::class)->accumulatedReading($plan->equipment);

        app(MaintenancePlanService::class)->recordManualExecution(
            plan: $plan,
            actor: auth()->user(),
            completedAt: now(),
            completedMeter: $meter,
        );

        Notification::make()
            ->title('Mantenimiento registrado')
            ->body('Último a las '.number_format($meter, 0).' h. Próximo en '.number_format((float) $plan->meter_interval, 0).' h.')
            ->success()
            ->send();
    }

    /**
     * Arma la OT preventiva de la fila. Reutiliza el mismo generador de siempre, así
     * que la OT nace con sus tareas y checklist congelados. No duplica: si el plan ya
     * tiene una OT abierta, avisa en vez de crear otra.
     */
    public function crearOt(string $planId): void
    {
        if (! (auth()->user()?->is_super_admin || auth()->user()?->can('work-orders.create'))) {
            return;
        }

        $plan = MaintenancePlan::with(['equipment', 'schedule'])->find($planId);

        if ($plan === null) {
            return;
        }

        $generator = app(PreventiveWorkOrderGenerator::class);

        if ($generator->hasOpenWorkOrder($plan)) {
            Notification::make()
                ->title('Ya hay una OT abierta para esta tarea')
                ->warning()
                ->send();

            return;
        }

        try {
            $workOrder = $generator->generate($plan, auth()->user());

            Notification::make()
                ->title("OT {$workOrder->work_order_number} creada")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo crear la OT')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ── Interno ───────────────────────────────────────────────────────────────

    private function seedControlCell(string $planId, string $field, int|float|null $value): void
    {
        if (! array_key_exists($field, $this->controlDraft[$planId] ?? [])) {
            $this->controlDraft[$planId][$field] = $value === null ? '' : (string) (0 + $value);
        }
    }

    /** Próximo mtto = último + frecuencia (último = 0 si nunca se ha hecho). */
    private function recomputeNextDue(MaintenancePlan $plan): void
    {
        $schedule = $plan->schedule;

        if ($schedule === null || $plan->meter_interval === null) {
            return;
        }

        $base = $schedule->last_completed_meter ?? 0.0;

        $schedule->update(['next_due_meter' => $base + $plan->meter_interval]);
    }

    /** Quién puede editar celdas y registrar mantenimientos en el tablero. */
    public function canWriteControl(): bool
    {
        return (bool) (auth()->user()?->is_super_admin || auth()->user()?->can('maintenance-plans.update'));
    }

    /** Quién puede armar la OT desde el tablero. */
    public function canCreateOtFromControl(): bool
    {
        return (bool) (auth()->user()?->is_super_admin || auth()->user()?->can('work-orders.create'));
    }
}
