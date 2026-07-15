<?php

use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Domain\Maintenance\Services\MaintenancePlanService;
use App\Domain\Maintenance\Services\PreventiveWorkOrderGenerator;
use App\Exceptions\BusinessRuleException;
use App\Models\ComponentHistory;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

/**
 * Un plan que apunta a una pieza, no al equipo entero.
 *
 * «Cambio de aceite de la unidad de potencia cada 500 h» reutiliza TODO el motor que
 * ya existía para «revisión general de la prensa cada 500 h» — el horómetro sigue
 * siendo el del equipo, el generador diario sigue siendo el mismo, el ciclo de cierre
 * sigue siendo el mismo. Lo único nuevo es que el plan puede decir a cuál pieza
 * pertenece, y que al completarse deja constancia en la bitácora del componente.
 */
function componentMeterPlan(Equipment $equipment, EquipmentComponent $component, array $schedule = [], array $overrides = []): MaintenancePlan
{
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $equipment->tenant_id,
        'equipment_id' => $equipment->id,
        'equipment_component_id' => $component->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
        'is_active' => true,
        ...$overrides,
    ]);

    MaintenanceSchedule::factory()->create([
        'tenant_id' => $plan->tenant_id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => null,
        'next_due_meter' => 500,
        ...$schedule,
    ]);

    return $plan->refresh();
}

beforeEach(function (): void {
    $this->generator = app(PreventiveWorkOrderGenerator::class);
    $this->tenant = Tenant::factory()->create();
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);
    $this->component = EquipmentComponent::factory()->forEquipment($this->equipment)->create([
        'name' => 'Unidad de potencia',
    ]);
    $this->actor = User::factory()->create();
});

// ── El plan sabe a cuál pieza pertenece ──────────────────────────────────────

it('creates a plan scoped to a component of the equipment', function (): void {
    $plan = app(MaintenancePlanService::class)->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'equipment_component_id' => $this->component->id,
        'name' => 'Cambio de aceite',
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
    ], $this->actor);

    expect($plan->isComponentScoped())->toBeTrue()
        ->and($plan->equipmentComponent->name)->toBe('Unidad de potencia');
});

it('refuses a component that belongs to a different equipment', function (): void {
    $otherEquipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    $foreignComponent = EquipmentComponent::factory()->forEquipment($otherEquipment)->create();

    expect(fn () => app(MaintenancePlanService::class)->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'equipment_component_id' => $foreignComponent->id,
        'name' => 'Plan mal armado',
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
    ], $this->actor))->toThrow(BusinessRuleException::class);
});

it('refuses a component from another tenant even by a guessed id', function (): void {
    $other = Tenant::factory()->create();
    $otherEquipment = Equipment::factory()->create(['tenant_id' => $other->id]);
    $foreignComponent = EquipmentComponent::factory()->forEquipment($otherEquipment)->create();

    expect(fn () => app(MaintenancePlanService::class)->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'equipment_component_id' => $foreignComponent->id,
        'name' => 'Plan cruzado',
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
    ], $this->actor))->toThrow(BusinessRuleException::class);
});

it('still enforces the same rule when the plan is edited, not just created', function (): void {
    $plan = componentMeterPlan($this->equipment, $this->component);
    $otherEquipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    $foreignComponent = EquipmentComponent::factory()->forEquipment($otherEquipment)->create();

    // Edit no pasa por el servicio (Filament actualiza el modelo directo) — la regla
    // tiene que vivir donde las dos rutas se crucen de verdad.
    expect(fn () => $plan->update(['equipment_component_id' => $foreignComponent->id]))
        ->toThrow(BusinessRuleException::class);
});

it('leaves an equipment-wide plan working exactly as before', function (): void {
    // Sin componente, es el plan de siempre. No debería fallar por nada nuevo.
    $plan = app(MaintenancePlanService::class)->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'name' => 'Revisión general',
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
    ], $this->actor);

    expect($plan->isComponentScoped())->toBeFalse();
});

// ── El generador ya sabe hacer esto — solo hace falta estampar la pieza ─────

it('stamps the component onto the work order it generates', function (): void {
    $plan = componentMeterPlan($this->equipment, $this->component, ['next_due_meter' => 300]);
    $this->equipment->update(['accumulated_meter_reading' => 300]);

    expect($this->generator->generateForTenant($this->tenant->id, $this->actor)['generated'])->toBe(1);

    $workOrder = WorkOrder::withoutGlobalScopes()->first();

    expect($workOrder->equipment_component_id)->toBe($this->component->id)
        ->and($workOrder->maintenance_plan_id)->toBe($plan->id)
        ->and($workOrder->equipment_id)->toBe($this->equipment->id);
});

it('does not touch equipment_component_id for a plan that has none', function (): void {
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => null,
        'next_due_meter' => 300,
    ]);
    $this->equipment->update(['accumulated_meter_reading' => 300]);

    $this->generator->generateForTenant($this->tenant->id, $this->actor);

    expect(WorkOrder::withoutGlobalScopes()->first()->equipment_component_id)->toBeNull();
});

// ── Al completarse, la bitácora del componente se escribe sola ──────────────

it('logs a component history entry when the plan completes', function (): void {
    $plan = componentMeterPlan($this->equipment, $this->component, ['next_due_meter' => 500]);
    $this->equipment->update(['accumulated_meter_reading' => 500]);

    $this->generator->generateForTenant($this->tenant->id, $this->actor);
    $workOrder = WorkOrder::withoutGlobalScopes()->first();

    $workOrder->update([
        'status' => WorkOrderStatus::Completed->value,
        'completed_by' => $this->actor->id,
        'actual_end_at' => now(),
    ]);

    $this->generator->recordCompletion($workOrder->refresh());

    $entry = ComponentHistory::withoutGlobalScopes()
        ->where('equipment_component_id', $this->component->id)
        ->sole();

    expect($entry->type)->toBe('maintenance')
        ->and($entry->user_id)->toBe($this->actor->id)
        ->and($entry->description)->toContain($plan->plan_number)
        ->and($entry->description)->toContain($workOrder->work_order_number);
});

it('does not write component history for an equipment-wide plan', function (): void {
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => null,
        'next_due_meter' => 500,
    ]);
    $this->equipment->update(['accumulated_meter_reading' => 500]);

    $this->generator->generateForTenant($this->tenant->id, $this->actor);
    $workOrder = WorkOrder::withoutGlobalScopes()->first();
    $workOrder->update(['status' => WorkOrderStatus::Completed->value, 'actual_end_at' => now()]);

    $this->generator->recordCompletion($workOrder->refresh());

    expect(ComponentHistory::withoutGlobalScopes()->count())->toBe(0);
});

// ── Cuántas horas van, cuántas faltan ────────────────────────────────────────

it('says how many hours remain before the component plan is due', function (): void {
    $plan = componentMeterPlan($this->equipment, $this->component, ['next_due_meter' => 500]);
    $this->equipment->update(['accumulated_meter_reading' => 320]);

    $remaining = app(EquipmentMeterReadingService::class)->metersRemaining($this->equipment, $plan);

    expect($remaining)->toBe(180.0);
});

it('never reports a negative remaining — overdue shows zero, not a debt', function (): void {
    $plan = componentMeterPlan($this->equipment, $this->component, ['next_due_meter' => 500]);
    $this->equipment->update(['accumulated_meter_reading' => 650]);

    expect(app(EquipmentMeterReadingService::class)->metersRemaining($this->equipment, $plan))->toBe(0.0);
});

it('says how many hours the component has carried since its last intervention', function (): void {
    $plan = componentMeterPlan($this->equipment, $this->component, [
        'next_due_meter' => 500,
        'last_completed_meter' => 200,
    ]);
    $this->equipment->update(['accumulated_meter_reading' => 350]);

    expect(app(EquipmentMeterReadingService::class)->metersSinceLastCompletion($this->equipment, $plan))
        ->toBe(150.0);
});

it('counts hours since activation when the plan has never run yet', function (): void {
    // Sin ejecución previa, el punto de partida es next_due_meter menos el intervalo:
    // el horómetro con el que el plan se activó.
    $plan = componentMeterPlan($this->equipment, $this->component, [
        'next_due_meter' => 500,
        'last_completed_meter' => null,
    ]);
    $this->equipment->update(['accumulated_meter_reading' => 120]);

    // baseline = 500 - 500 (meter_interval) = 0
    expect(app(EquipmentMeterReadingService::class)->metersSinceLastCompletion($this->equipment, $plan))
        ->toBe(120.0);
});

it('returns null for hours remaining on a plan that was never activated', function (): void {
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'equipment_component_id' => $this->component->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
        'is_active' => false,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => null,
        'next_due_meter' => null,
    ]);

    expect(app(EquipmentMeterReadingService::class)->metersRemaining($this->equipment, $plan))->toBeNull()
        ->and(app(EquipmentMeterReadingService::class)->metersSinceLastCompletion($this->equipment, $plan))->toBeNull();
});

// ── También por fecha, no solo por horómetro ─────────────────────────────────

it('lets a component plan be date-triggered instead of meter-triggered', function (): void {
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'equipment_component_id' => $this->component->id,
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'time_frequency' => 'monthly',
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => now()->addDays(3),
        'next_due_meter' => null,
    ]);

    expect($this->generator->generateForTenant($this->tenant->id, $this->actor)['generated'])->toBe(1);

    expect(WorkOrder::withoutGlobalScopes()->first()->equipment_component_id)->toBe($this->component->id);
});

// ── Multi-tenant ──────────────────────────────────────────────────────────────

it('never lets a component plan leak across tenants when generating', function (): void {
    $other = Tenant::factory()->create();
    $otherEquipment = Equipment::factory()->create(['tenant_id' => $other->id]);
    $otherComponent = EquipmentComponent::factory()->forEquipment($otherEquipment)->create();
    componentMeterPlan($otherEquipment, $otherComponent, ['next_due_meter' => 100]);
    $otherEquipment->update(['accumulated_meter_reading' => 100]);

    componentMeterPlan($this->equipment, $this->component, ['next_due_meter' => 300]);
    $this->equipment->update(['accumulated_meter_reading' => 300]);

    $result = $this->generator->generateForTenant($this->tenant->id, $this->actor);

    expect($result['generated'])->toBe(1)
        ->and(WorkOrder::withoutGlobalScopes()->where('tenant_id', $other->id)->count())->toBe(0);
});
