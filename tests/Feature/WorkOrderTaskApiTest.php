<?php

use App\Domain\Maintenance\Enums\MaintenanceChecklistItemType;
use App\Domain\Maintenance\Enums\WorkOrderTaskStatus;
use App\Domain\Maintenance\Services\WorkOrderTaskService;
use App\Models\Alert;
use App\Models\Equipment;
use App\Models\MaintenanceChecklistItem;
use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanTask;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

// ── Helpers ───────────────────────────────────────────────────────────────────

function taskApiActor(Tenant $tenant, array $abilities = ['*']): array
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $token = $user->createToken('test-token', $abilities);
    $token->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return ['user' => $user, 'token' => $token->plainTextToken];
}

function taskApiHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

/** Una OT preventiva con una tarea y dos ítems: un sí/no y una medición con rango. */
function workOrderWithChecklist(Tenant $tenant): WorkOrder
{
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
    ]);

    $planTask = MaintenancePlanTask::factory()->create([
        'tenant_id' => $tenant->id,
        'maintenance_plan_id' => $plan->id,
        'sort_order' => 1,
        'title' => 'Inspección de prensa',
    ]);

    MaintenanceChecklistItem::factory()->create([
        'tenant_id' => $tenant->id,
        'maintenance_plan_task_id' => $planTask->id,
        'sort_order' => 1,
        'label' => '¿Sin fugas?',
        'item_type' => MaintenanceChecklistItemType::Boolean->value,
        'is_required' => true,
        'expected_min' => null,
        'expected_max' => null,
    ]);

    MaintenanceChecklistItem::factory()->create([
        'tenant_id' => $tenant->id,
        'maintenance_plan_task_id' => $planTask->id,
        'sort_order' => 2,
        'label' => 'Vibración',
        'item_type' => MaintenanceChecklistItemType::Numeric->value,
        'unit' => 'mm/s',
        'expected_min' => 2.0,
        'expected_max' => 7.1,
        'is_required' => true,
    ]);

    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'maintenance_plan_id' => $plan->id,
    ]);

    app(WorkOrderTaskService::class)->copyFromPlan($workOrder, $plan);

    return $workOrder->refresh();
}

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $actor = taskApiActor($this->tenant);
    $this->user = $actor['user'];
    $this->token = $actor['token'];
    $this->workOrder = workOrderWithChecklist($this->tenant);
    $this->task = $this->workOrder->tasks()->first();
});

// ── Lectura ───────────────────────────────────────────────────────────────────

it('returns the tasks of a work order with their frozen checklist and progress', function (): void {
    $response = $this->withHeaders(taskApiHeaders($this->token))
        ->getJson("/api/v1/work-orders/{$this->workOrder->id}/tasks");

    $response->assertOk()
        ->assertJsonPath('data.0.title', 'Inspección de prensa')
        ->assertJsonPath('data.0.status', 'pending')
        ->assertJsonPath('data.0.checklist.1.label', 'Vibración')
        ->assertJsonPath('data.0.checklist.1.expected_range_label', '2 – 7.1 mm/s')
        ->assertJsonPath('data.0.checklist.1.is_answered', false)
        ->assertJsonPath('meta.progress.total', 1)
        ->assertJsonPath('meta.progress.resolved', 0)
        ->assertJsonPath('meta.missing_required', 2);
});

it('refuses a token without the work-orders ability', function (): void {
    $actor = taskApiActor($this->tenant, ['equipment.read']);

    $this->withHeaders(taskApiHeaders($actor['token']))
        ->getJson("/api/v1/work-orders/{$this->workOrder->id}/tasks")
        ->assertForbidden();
});

it('never exposes the tasks of another tenant work order', function (): void {
    $other = Tenant::factory()->create();
    $otherActor = taskApiActor($other);

    $this->withHeaders(taskApiHeaders($otherActor['token']))
        ->getJson("/api/v1/work-orders/{$this->workOrder->id}/tasks")
        ->assertNotFound();
});

// ── Ejecución ─────────────────────────────────────────────────────────────────

it('starts a task', function (): void {
    $this->withHeaders(taskApiHeaders($this->token))
        ->postJson("/api/v1/work-orders/{$this->workOrder->id}/tasks/{$this->task->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');
});

it('records a boolean answer', function (): void {
    $item = $this->task->checklistResults()->where('label', '¿Sin fugas?')->first();

    $this->withHeaders(taskApiHeaders($this->token))
        ->postJson(
            "/api/v1/work-orders/{$this->workOrder->id}/tasks/{$this->task->id}/checklist/{$item->id}",
            ['value' => true],
        )
        ->assertOk()
        ->assertJsonPath('data.is_answered', true)
        ->assertJsonPath('data.display_value', 'Sí')
        ->assertJsonPath('data.is_out_of_range', false);
});

it('flags an out-of-range reading and raises an alert', function (): void {
    $item = $this->task->checklistResults()->where('label', 'Vibración')->first();

    $this->withHeaders(taskApiHeaders($this->token))
        ->postJson(
            "/api/v1/work-orders/{$this->workOrder->id}/tasks/{$this->task->id}/checklist/{$item->id}",
            ['value' => 12.4],
        )
        ->assertOk()
        ->assertJsonPath('data.is_out_of_range', true)
        ->assertJsonPath('data.deviation', 5.3);

    expect(Alert::withoutGlobalScopes()->count())->toBe(1);
});

it('rejects a non-numeric value on a numeric item', function (): void {
    $item = $this->task->checklistResults()->where('label', 'Vibración')->first();

    $this->withHeaders(taskApiHeaders($this->token))
        ->postJson(
            "/api/v1/work-orders/{$this->workOrder->id}/tasks/{$this->task->id}/checklist/{$item->id}",
            ['value' => 'ok'],
        )
        ->assertStatus(409)
        ->assertJsonPath('message', 'El ítem «Vibración» requiere un valor numérico.');
});

it('refuses to complete a task with unanswered required items', function (): void {
    $this->withHeaders(taskApiHeaders($this->token))
        ->postJson("/api/v1/work-orders/{$this->workOrder->id}/tasks/{$this->task->id}/complete")
        // BusinessRuleException renders as 409 Conflict.
        ->assertStatus(409)
        ->assertJsonPath('message', fn (string $m) => str_contains($m, 'medición'));
});

it('completes a task once every required item carries a value', function (): void {
    foreach ($this->task->checklistResults as $item) {
        $this->withHeaders(taskApiHeaders($this->token))->postJson(
            "/api/v1/work-orders/{$this->workOrder->id}/tasks/{$this->task->id}/checklist/{$item->id}",
            ['value' => $item->item_type === MaintenanceChecklistItemType::Numeric ? 5.0 : true],
        )->assertOk();
    }

    $this->withHeaders(taskApiHeaders($this->token))
        ->postJson("/api/v1/work-orders/{$this->workOrder->id}/tasks/{$this->task->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'done');

    expect($this->task->refresh()->status)->toBe(WorkOrderTaskStatus::Done);
});

it('demands a reason to skip a task', function (): void {
    $this->withHeaders(taskApiHeaders($this->token))
        ->postJson("/api/v1/work-orders/{$this->workOrder->id}/tasks/{$this->task->id}/skip", ['reason' => ''])
        ->assertStatus(422);

    $this->withHeaders(taskApiHeaders($this->token))
        ->postJson(
            "/api/v1/work-orders/{$this->workOrder->id}/tasks/{$this->task->id}/skip",
            ['reason' => 'Sin repuesto en almacén'],
        )
        ->assertOk()
        ->assertJsonPath('data.status', 'skipped')
        ->assertJsonPath('data.skipped_reason', 'Sin repuesto en almacén');
});

it('adds an ad-hoc task to a work order', function (): void {
    $this->withHeaders(taskApiHeaders($this->token))
        ->postJson("/api/v1/work-orders/{$this->workOrder->id}/tasks", [
            'title' => 'Cambiar empaque encontrado dañado',
            'estimated_minutes' => 30,
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Cambiar empaque encontrado dañado');

    expect($this->workOrder->tasks()->count())->toBe(2);
});
