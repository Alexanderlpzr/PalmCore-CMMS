<?php

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Maintenance\Enums\MaintenanceChecklistItemType;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Domain\Maintenance\Services\PreventiveWorkOrderGenerator;
use App\Events\WorkOrderStatusChanged;
use App\Jobs\GeneratePreventiveWorkOrdersJob;
use App\Models\Equipment;
use App\Models\MaintenanceChecklistItem;
use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanTask;
use App\Models\MaintenanceSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

// ── Helpers ───────────────────────────────────────────────────────────────────

function calendarPlan(Equipment $equipment, array $schedule = [], array $overrides = []): MaintenancePlan
{
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $equipment->tenant_id,
        'equipment_id' => $equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'is_active' => true,
        ...$overrides,
    ]);

    MaintenanceSchedule::factory()->create([
        'tenant_id' => $plan->tenant_id,
        'maintenance_plan_id' => $plan->id,
        'next_due_meter' => null,
        ...$schedule,
    ]);

    return $plan->refresh();
}

beforeEach(function (): void {
    $this->generator = app(PreventiveWorkOrderGenerator::class);
    $this->tenant = Tenant::factory()->create();
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'criticality' => EquipmentCriticality::Critical->value,
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);
    $this->actor = User::factory()->create();
});

// ── Lo que decide si un plan genera trabajo ──────────────────────────────────

it('generates a work order for a plan due inside the lead window', function (): void {
    $plan = calendarPlan($this->equipment, ['next_due_at' => now()->addDays(3)]);

    $result = $this->generator->generateForTenant($this->tenant->id, $this->actor);

    expect($result['generated'])->toBe(1);

    $workOrder = WorkOrder::withoutGlobalScopes()->first();

    expect($workOrder->maintenance_plan_id)->toBe($plan->id)
        ->and($workOrder->work_order_type)->toBe(WorkOrderType::Preventive)
        ->and($workOrder->status)->toBe(WorkOrderStatus::Draft)
        ->and($plan->refresh()->last_generated_at)->not->toBeNull();
});

it('leaves a plan alone while its due date is still beyond the lead window', function (): void {
    calendarPlan($this->equipment, ['next_due_at' => now()->addDays(30)]);

    $result = $this->generator->generateForTenant($this->tenant->id, $this->actor);

    expect($result['generated'])->toBe(0)
        ->and($result['skipped'])->toBe(1)
        ->and(WorkOrder::withoutGlobalScopes()->count())->toBe(0);
});

it('generates for a plan that is already overdue', function (): void {
    calendarPlan($this->equipment, ['next_due_at' => now()->subDays(20)]);

    expect($this->generator->generateForTenant($this->tenant->id, $this->actor)['generated'])->toBe(1);
});

it('ignores inactive plans', function (): void {
    calendarPlan($this->equipment, ['next_due_at' => now()], ['is_active' => false]);

    expect($this->generator->generateForTenant($this->tenant->id, $this->actor)['generated'])->toBe(0);
});

it('respects a plan paused because its equipment is out of service', function (): void {
    $this->equipment->update(['is_active' => false]);

    calendarPlan(
        $this->equipment,
        ['next_due_at' => now()],
        ['pause_when_equipment_inactive' => true],
    );

    expect($this->generator->generateForTenant($this->tenant->id, $this->actor)['generated'])->toBe(0);
});

// ── Idempotencia: el job corre todos los días ────────────────────────────────

it('never generates a second work order while the first one is still open', function (): void {
    calendarPlan($this->equipment, ['next_due_at' => now()]);

    $this->generator->generateForTenant($this->tenant->id, $this->actor);
    $second = $this->generator->generateForTenant($this->tenant->id, $this->actor);

    expect($second['generated'])->toBe(0)
        ->and(WorkOrder::withoutGlobalScopes()->count())->toBe(1);
});

// ── La OT nace lista para ejecutarse ─────────────────────────────────────────

it('hands the técnico a work order that already carries the frozen checklist', function (): void {
    $plan = calendarPlan($this->equipment, ['next_due_at' => now()]);

    $task = MaintenancePlanTask::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_id' => $plan->id,
        'title' => 'Lubricar rodamientos',
    ]);
    MaintenanceChecklistItem::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_task_id' => $task->id,
        'label' => 'Temperatura de rodamiento',
        'item_type' => MaintenanceChecklistItemType::Numeric->value,
        'expected_min' => 40,
        'expected_max' => 80,
        'is_required' => true,
    ]);

    $this->generator->generateForTenant($this->tenant->id, $this->actor);

    $workOrder = WorkOrder::withoutGlobalScopes()->first();

    expect($workOrder->tasks()->count())->toBe(1)
        ->and($workOrder->tasks()->first()->title)->toBe('Lubricar rodamientos')
        ->and($workOrder->checklistResults()->count())->toBe(1);
});

it('queues a critical asset ahead of a spare one', function (): void {
    calendarPlan($this->equipment, ['next_due_at' => now()]);

    $spare = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'criticality' => EquipmentCriticality::Low->value,
    ]);
    calendarPlan($spare, ['next_due_at' => now()]);

    $this->generator->generateForTenant($this->tenant->id, $this->actor);

    expect(WorkOrder::withoutGlobalScopes()->where('equipment_id', $this->equipment->id)->value('priority'))
        ->toBe(WorkOrderPriority::P2High)
        ->and(WorkOrder::withoutGlobalScopes()->where('equipment_id', $spare->id)->value('priority'))
        ->toBe(WorkOrderPriority::P5Planned);
});

// ── Planes por horómetro: la proyección decide ───────────────────────────────

it('generates when the equipment pace says the meter target lands inside the window', function (): void {
    $meters = app(EquipmentMeterReadingService::class);
    $meters->record($this->equipment, 1_000, $this->actor, recordedAt: now()->subDays(10));
    $meters->record($this->equipment->refresh(), 1_200, $this->actor, recordedAt: now()); // 20 h/día

    // Acumulado 200 h, meta 300 h → faltan 5 días: entra en la ventana de 7.
    calendarPlan(
        $this->equipment->refresh(),
        ['next_due_at' => null, 'next_due_meter' => 300],
        ['trigger_source' => MaintenanceTriggerSource::Meter->value, 'meter_interval' => 300],
    );

    expect($this->generator->generateForTenant($this->tenant->id, $this->actor)['generated'])->toBe(1);
});

it('waits when the meter target is still far away at the current pace', function (): void {
    $meters = app(EquipmentMeterReadingService::class);
    $meters->record($this->equipment, 1_000, $this->actor, recordedAt: now()->subDays(10));
    $meters->record($this->equipment->refresh(), 1_200, $this->actor, recordedAt: now()); // 20 h/día

    // Acumulado 200 h, meta 1.000 h → faltan 40 días.
    calendarPlan(
        $this->equipment->refresh(),
        ['next_due_at' => null, 'next_due_meter' => 1_000],
        ['trigger_source' => MaintenanceTriggerSource::Meter->value, 'meter_interval' => 1_000],
    );

    expect($this->generator->generateForTenant($this->tenant->id, $this->actor)['generated'])->toBe(0);
});

it('still generates for a meter plan already past its target even with no measurable pace', function (): void {
    $this->equipment->update(['accumulated_meter_reading' => 1_500]);

    calendarPlan(
        $this->equipment->refresh(),
        ['next_due_at' => null, 'next_due_meter' => 1_000],
        ['trigger_source' => MaintenanceTriggerSource::Meter->value, 'meter_interval' => 1_000],
    );

    expect($this->generator->generateForTenant($this->tenant->id, $this->actor)['generated'])->toBe(1);
});

// ── Anticipación por horas de horómetro (no por días) ────────────────────────

it('generates a meter plan when the remaining hours fall within its configured lead', function (): void {
    // Faltan 150 h para las 5.000 h; el plan pide la OT con 200 h de anticipación.
    $this->equipment->update(['accumulated_meter_reading' => 4_850]);

    calendarPlan(
        $this->equipment->refresh(),
        ['next_due_at' => null, 'next_due_meter' => 5_000],
        ['trigger_source' => MaintenanceTriggerSource::Meter->value, 'meter_interval' => 5_000, 'meter_lead_hours' => 200],
    );

    expect($this->generator->generateForTenant($this->tenant->id, $this->actor)['generated'])->toBe(1);
});

it('waits on a meter plan while the remaining hours are still beyond its lead', function (): void {
    // Faltan 300 h > 200 h de anticipación: todavía no.
    $this->equipment->update(['accumulated_meter_reading' => 4_700]);

    calendarPlan(
        $this->equipment->refresh(),
        ['next_due_at' => null, 'next_due_meter' => 5_000],
        ['trigger_source' => MaintenanceTriggerSource::Meter->value, 'meter_interval' => 5_000, 'meter_lead_hours' => 200],
    );

    expect($this->generator->generateForTenant($this->tenant->id, $this->actor)['generated'])->toBe(0);
});

it('falls back to the 150-hour default lead when the plan configures none', function (): void {
    // Sin meter_lead_hours propio: faltan 100 h ≤ 150 h por defecto → genera.
    $this->equipment->update(['accumulated_meter_reading' => 4_900]);

    calendarPlan(
        $this->equipment->refresh(),
        ['next_due_at' => null, 'next_due_meter' => 5_000],
        ['trigger_source' => MaintenanceTriggerSource::Meter->value, 'meter_interval' => 5_000, 'meter_lead_hours' => null],
    );

    expect($this->generator->generateForTenant($this->tenant->id, $this->actor)['generated'])->toBe(1);
});

it('does not generate for a meter plan whose remaining hours exceed the default lead', function (): void {
    // Sin lead propio y faltan 500 h > 150 h por defecto → espera.
    $this->equipment->update(['accumulated_meter_reading' => 4_500]);

    calendarPlan(
        $this->equipment->refresh(),
        ['next_due_at' => null, 'next_due_meter' => 5_000],
        ['trigger_source' => MaintenanceTriggerSource::Meter->value, 'meter_interval' => 5_000, 'meter_lead_hours' => null],
    );

    expect($this->generator->generateForTenant($this->tenant->id, $this->actor)['generated'])->toBe(0);
});

// ── El ciclo se cierra ────────────────────────────────────────────────────────

it('advances the plan schedule when the preventive is recorded as done', function (): void {
    $plan = calendarPlan($this->equipment, ['next_due_at' => now()->subDay()]);
    $dueBefore = $plan->schedule->next_due_at;

    $this->generator->generateForTenant($this->tenant->id, $this->actor);

    $workOrder = WorkOrder::withoutGlobalScopes()->first();
    $workOrder->update([
        'status' => WorkOrderStatus::Completed->value,
        'actual_end_at' => now(),
    ]);

    $this->generator->recordCompletion($workOrder->refresh());

    $schedule = $plan->schedule->refresh();

    expect($schedule->times_executed)->toBe(1)
        ->and($schedule->last_work_order_id)->toBe($workOrder->id)
        ->and($schedule->next_due_at->gt($dueBefore))->toBeTrue();
});

it('closes the loop from the WorkOrderStatusChanged event', function (): void {
    $plan = calendarPlan($this->equipment, ['next_due_at' => now()->subDay()]);

    $this->generator->generateForTenant($this->tenant->id, $this->actor);

    $workOrder = WorkOrder::withoutGlobalScopes()->first();
    $workOrder->update(['status' => WorkOrderStatus::Completed->value, 'actual_end_at' => now()]);

    event(new WorkOrderStatusChanged($workOrder->refresh(), WorkOrderStatus::Completed));

    expect($plan->schedule->refresh()->times_executed)->toBe(1);
});

it('ignores the event for a work order that came from no plan', function (): void {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_id' => null,
    ]);

    event(new WorkOrderStatusChanged($workOrder, WorkOrderStatus::Completed));

    expect(MaintenanceSchedule::withoutGlobalScopes()->count())->toBe(0);
})->throwsNoExceptions();

// ── El job ────────────────────────────────────────────────────────────────────

it('runs end to end through the scheduled job', function (): void {
    $owner = User::factory()->create();
    $this->tenant->users()->attach($owner->id, ['is_owner' => true, 'joined_at' => now()]);

    calendarPlan($this->equipment, ['next_due_at' => now()]);

    (new GeneratePreventiveWorkOrdersJob($this->tenant->id))
        ->handle(app(PreventiveWorkOrderGenerator::class));

    expect(WorkOrder::withoutGlobalScopes()->count())->toBe(1)
        ->and(WorkOrder::withoutGlobalScopes()->value('created_by'))->toBe($owner->id);
});

// ── Multi-tenant ──────────────────────────────────────────────────────────────

it('never generates work orders for another tenant plans', function (): void {
    $other = Tenant::factory()->create();
    $otherEquipment = Equipment::factory()->create(['tenant_id' => $other->id]);
    calendarPlan($otherEquipment, ['next_due_at' => now()]);

    calendarPlan($this->equipment, ['next_due_at' => now()]);

    $result = $this->generator->generateForTenant($this->tenant->id, $this->actor);

    expect($result['generated'])->toBe(1)
        ->and(WorkOrder::withoutGlobalScopes()->where('tenant_id', $other->id)->count())->toBe(0);
});
