<?php

use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Maintenance\Enums\MaintenanceChecklistItemType;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderTaskStatus;
use App\Domain\Maintenance\Exceptions\ChecklistIncompleteException;
use App\Domain\Maintenance\Exceptions\InvalidChecklistValueException;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Domain\Maintenance\Services\WorkOrderTaskService;
use App\Models\Alert;
use App\Models\Equipment;
use App\Models\MaintenanceChecklistItem;
use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanTask;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistResult;
use App\Models\WorkOrderTask;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * A plan modelled on the real Esterilizador preventive: one task with a
 * pass/fail check and a measured value with tolerances.
 */
function planWithChecklist(Equipment $equipment): MaintenancePlan
{
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $equipment->tenant_id,
        'equipment_id' => $equipment->id,
    ]);

    $task = MaintenancePlanTask::factory()->create([
        'tenant_id' => $plan->tenant_id,
        'maintenance_plan_id' => $plan->id,
        'sort_order' => 1,
        'title' => 'Esterilizador — mtto e inspección válvulas Bray',
        'estimated_minutes' => 45,
    ]);

    MaintenanceChecklistItem::factory()->create([
        'tenant_id' => $plan->tenant_id,
        'maintenance_plan_task_id' => $task->id,
        'sort_order' => 1,
        'label' => '¿Válvulas sin fuga de vapor?',
        'item_type' => MaintenanceChecklistItemType::Boolean->value,
        'is_required' => true,
        'unit' => null,
        'expected_min' => null,
        'expected_max' => null,
    ]);

    MaintenanceChecklistItem::factory()->create([
        'tenant_id' => $plan->tenant_id,
        'maintenance_plan_task_id' => $task->id,
        'sort_order' => 2,
        'label' => 'Espesor de camisa',
        'item_type' => MaintenanceChecklistItemType::Numeric->value,
        'unit' => 'mm',
        'expected_min' => 8.0,
        'expected_max' => 12.0,
        'is_required' => true,
    ]);

    return $plan;
}

function preventiveWorkOrder(Equipment $equipment, MaintenancePlan $plan): WorkOrder
{
    return WorkOrder::factory()->create([
        'tenant_id' => $equipment->tenant_id,
        'equipment_id' => $equipment->id,
        'plant_id' => $equipment->plant_id,
        'area_id' => $equipment->area_id,
        'maintenance_plan_id' => $plan->id,
    ]);
}

beforeEach(function (): void {
    $this->service = app(WorkOrderTaskService::class);
    $this->tenant = Tenant::factory()->create();
    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actor = User::factory()->create();
});

// ── copyFromPlan ──────────────────────────────────────────────────────────────

it('copies plan tasks and checklist items onto the work order', function (): void {
    $plan = planWithChecklist($this->equipment);
    $workOrder = preventiveWorkOrder($this->equipment, $plan);

    $copied = $this->service->copyFromPlan($workOrder, $plan);

    expect($copied)->toBe(1)
        ->and($workOrder->tasks()->count())->toBe(1);

    $task = $workOrder->tasks()->first();

    expect($task->title)->toBe('Esterilizador — mtto e inspección válvulas Bray')
        ->and($task->estimated_minutes)->toBe(45)
        ->and($task->status)->toBe(WorkOrderTaskStatus::Pending)
        ->and($task->checklistResults()->count())->toBe(2);

    $numeric = $task->checklistResults()->where('label', 'Espesor de camisa')->first();

    expect($numeric->unit)->toBe('mm')
        ->and($numeric->expected_min)->toBe(8.0)
        ->and($numeric->expected_max)->toBe(12.0)
        ->and($numeric->is_required)->toBeTrue()
        ->and($numeric->isAnswered())->toBeFalse();
});

it('freezes the copy: editing the plan afterwards never rewrites the work order', function (): void {
    $plan = planWithChecklist($this->equipment);
    $workOrder = preventiveWorkOrder($this->equipment, $plan);

    $this->service->copyFromPlan($workOrder, $plan);

    // The planner rewrites the plan the next day.
    $planTask = MaintenancePlanTask::where('maintenance_plan_id', $plan->id)->first();
    $planTask->update(['title' => 'TÍTULO REESCRITO', 'estimated_minutes' => 999]);
    MaintenanceChecklistItem::where('label', 'Espesor de camisa')->first()
        ->update(['expected_min' => 1.0, 'expected_max' => 2.0]);

    $task = $workOrder->tasks()->first();
    $numeric = $task->checklistResults()->where('label', 'Espesor de camisa')->first();

    expect($task->title)->toBe('Esterilizador — mtto e inspección válvulas Bray')
        ->and($task->estimated_minutes)->toBe(45)
        ->and($numeric->expected_min)->toBe(8.0)
        ->and($numeric->expected_max)->toBe(12.0);
});

it('is idempotent — re-running the copy never duplicates the work', function (): void {
    $plan = planWithChecklist($this->equipment);
    $workOrder = preventiveWorkOrder($this->equipment, $plan);

    $this->service->copyFromPlan($workOrder, $plan);
    $second = $this->service->copyFromPlan($workOrder, $plan);

    expect($second)->toBe(0)
        ->and($workOrder->tasks()->count())->toBe(1)
        ->and(WorkOrderChecklistResult::withoutGlobalScopes()->count())->toBe(2);
});

// ── Recording values ──────────────────────────────────────────────────────────

it('records a boolean answer', function (): void {
    $plan = planWithChecklist($this->equipment);
    $workOrder = preventiveWorkOrder($this->equipment, $plan);
    $this->service->copyFromPlan($workOrder, $plan);

    $result = $workOrder->checklistResults()
        ->where('item_type', MaintenanceChecklistItemType::Boolean->value)->first();

    $this->service->recordChecklistResult($result, $this->actor, ['value' => true]);

    $result->refresh();

    expect($result->value_boolean)->toBeTrue()
        ->and($result->isAnswered())->toBeTrue()
        ->and($result->recorded_by)->toBe($this->actor->id)
        ->and($result->is_out_of_range)->toBeFalse()
        ->and($result->displayValue())->toBe('Sí');
});

it('records an in-range numeric value without raising an alert', function (): void {
    $plan = planWithChecklist($this->equipment);
    $workOrder = preventiveWorkOrder($this->equipment, $plan);
    $this->service->copyFromPlan($workOrder, $plan);

    $result = $workOrder->checklistResults()->where('label', 'Espesor de camisa')->first();

    $this->service->recordChecklistResult($result, $this->actor, ['value' => 10.5]);

    $result->refresh();

    expect($result->value_numeric)->toBe(10.5)
        ->and($result->is_out_of_range)->toBeFalse()
        ->and($result->displayValue())->toBe('10.5 mm')
        ->and(Alert::withoutGlobalScopes()->count())->toBe(0);
});

it('flags an out-of-range value and raises a reliability alert', function (): void {
    $plan = planWithChecklist($this->equipment);
    $workOrder = preventiveWorkOrder($this->equipment, $plan);
    $this->service->copyFromPlan($workOrder, $plan);

    $result = $workOrder->checklistResults()->where('label', 'Espesor de camisa')->first();

    // Camisa desgastada: 2.5 mm contra un mínimo de 8.0.
    $this->service->recordChecklistResult($result, $this->actor, ['value' => 2.5]);

    $result->refresh();

    expect($result->is_out_of_range)->toBeTrue()
        ->and($result->deviation())->toBe(-5.5);

    $alert = Alert::withoutGlobalScopes()->first();

    expect($alert)->not->toBeNull()
        ->and($alert->category)->toBe(AlertCategory::Reliability)
        ->and($alert->title)->toContain('Espesor de camisa')
        ->and($alert->metadata['deviation'])->toBe(-5.5);
});

it('rejects a non-numeric value on a numeric item', function (): void {
    $plan = planWithChecklist($this->equipment);
    $workOrder = preventiveWorkOrder($this->equipment, $plan);
    $this->service->copyFromPlan($workOrder, $plan);

    $result = $workOrder->checklistResults()->where('label', 'Espesor de camisa')->first();

    expect(fn () => $this->service->recordChecklistResult($result, $this->actor, ['value' => 'ok']))
        ->toThrow(InvalidChecklistValueException::class);
});

// ── Task lifecycle ────────────────────────────────────────────────────────────

it('refuses to complete a task with unanswered required items', function (): void {
    $plan = planWithChecklist($this->equipment);
    $workOrder = preventiveWorkOrder($this->equipment, $plan);
    $this->service->copyFromPlan($workOrder, $plan);

    $task = $workOrder->tasks()->first();

    expect(fn () => $this->service->completeTask($task, $this->actor))
        ->toThrow(ChecklistIncompleteException::class);

    expect($task->refresh()->status)->toBe(WorkOrderTaskStatus::Pending);
});

it('completes a task once every required item carries a value', function (): void {
    $plan = planWithChecklist($this->equipment);
    $workOrder = preventiveWorkOrder($this->equipment, $plan);
    $this->service->copyFromPlan($workOrder, $plan);

    $task = $workOrder->tasks()->first();

    foreach ($task->checklistResults as $result) {
        $this->service->recordChecklistResult($result, $this->actor, [
            'value' => $result->item_type === MaintenanceChecklistItemType::Numeric ? 10.0 : true,
        ]);
    }

    $task = $this->service->completeTask($task, $this->actor);

    expect($task->status)->toBe(WorkOrderTaskStatus::Done)
        ->and($task->completed_by)->toBe($this->actor->id)
        ->and($task->completed_at)->not->toBeNull();
});

it('ignores optional items when deciding whether a task can be completed', function (): void {
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);
    $task = WorkOrderTask::factory()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $workOrder->id,
    ]);
    WorkOrderChecklistResult::factory()->optional()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_task_id' => $task->id,
    ]);

    $task = $this->service->completeTask($task, $this->actor);

    expect($task->status)->toBe(WorkOrderTaskStatus::Done);
});

it('requires a reason to skip a task', function (): void {
    $task = WorkOrderTask::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(fn () => $this->service->skipTask($task, $this->actor, '   '))
        ->toThrow(InvalidArgumentException::class);

    $task = $this->service->skipTask($task, $this->actor, 'Sin repuesto en almacén');

    expect($task->status)->toBe(WorkOrderTaskStatus::Skipped)
        ->and($task->skipped_reason)->toBe('Sin repuesto en almacén');
});

// ── The completion guard on the OT ────────────────────────────────────────────

it('blocks completing a work order that still has unresolved tasks', function (): void {
    $plan = planWithChecklist($this->equipment);
    $workOrder = preventiveWorkOrder($this->equipment, $plan);
    $this->service->copyFromPlan($workOrder, $plan);

    expect(fn () => $this->service->assertReadyToComplete($workOrder))
        ->toThrow(ChecklistIncompleteException::class);
});

it('allows completing a work order once every task is resolved', function (): void {
    $plan = planWithChecklist($this->equipment);
    $workOrder = preventiveWorkOrder($this->equipment, $plan);
    $this->service->copyFromPlan($workOrder, $plan);

    $task = $workOrder->tasks()->first();
    foreach ($task->checklistResults as $result) {
        $this->service->recordChecklistResult($result, $this->actor, [
            'value' => $result->item_type === MaintenanceChecklistItemType::Numeric ? 10.0 : true,
        ]);
    }
    $this->service->completeTask($task, $this->actor);

    $this->service->assertReadyToComplete($workOrder);

    expect($this->service->progress($workOrder))->toBe(['resolved' => 1, 'total' => 1]);
});

it('a skipped task does not drag its unanswered checklist into the completion guard', function (): void {
    $plan = planWithChecklist($this->equipment);
    $workOrder = preventiveWorkOrder($this->equipment, $plan);
    $this->service->copyFromPlan($workOrder, $plan);

    $this->service->skipTask($workOrder->tasks()->first(), $this->actor, 'Equipo no disponible');

    $this->service->assertReadyToComplete($workOrder);

    expect($this->service->missingRequiredResults($workOrder))->toBe(0);
});

it('leaves work orders without tasks free to complete (correctivos ad-hoc)', function (): void {
    $workOrder = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->service->assertReadyToComplete($workOrder);
})->throwsNoExceptions();

it('blocks the Completed transition through WorkOrderService', function (): void {
    $plan = planWithChecklist($this->equipment);
    $workOrder = preventiveWorkOrder($this->equipment, $plan);
    $workOrder->update(['status' => WorkOrderStatus::InProgress->value]);
    $this->service->copyFromPlan($workOrder, $plan);

    expect(fn () => app(WorkOrderService::class)->transition(
        $workOrder->refresh(),
        WorkOrderStatus::Completed,
        $this->actor,
    ))->toThrow(ChecklistIncompleteException::class);

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::InProgress);
});

it('a work order created from a plan arrives with its checklist already frozen', function (): void {
    $plan = planWithChecklist($this->equipment);

    $workOrder = app(WorkOrderService::class)->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'maintenance_plan_id' => $plan->id,
        'work_order_type' => 'preventive',
        'priority' => 'p3_medium',
        'title' => 'Preventivo Esterilizador',
        'description' => 'Rutina mensual generada desde el plan.',
    ], $this->actor);

    expect($workOrder->tasks()->count())->toBe(1)
        ->and($workOrder->checklistResults()->count())->toBe(2);
});

// ── Multi-tenant ──────────────────────────────────────────────────────────────

it('never leaks tasks across tenants', function (): void {
    $plan = planWithChecklist($this->equipment);
    $workOrder = preventiveWorkOrder($this->equipment, $plan);
    $this->service->copyFromPlan($workOrder, $plan);

    $otherTenant = Tenant::factory()->create();
    $otherEquipment = Equipment::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherPlan = planWithChecklist($otherEquipment);
    $otherWorkOrder = preventiveWorkOrder($otherEquipment, $otherPlan);
    $this->service->copyFromPlan($otherWorkOrder, $otherPlan);

    expect(WorkOrderTask::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(1)
        ->and(WorkOrderTask::withoutGlobalScopes()->where('tenant_id', $otherTenant->id)->count())->toBe(1);

    expect($workOrder->tasks()->pluck('tenant_id')->unique()->all())->toBe([$this->tenant->id]);
});
