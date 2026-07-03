<?php

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Services\WorkOrderMissionPresenter;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanTask;
use App\Models\MaintenanceRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Models\WorkOrderSignature;

function presenter(): WorkOrderMissionPresenter
{
    return app(WorkOrderMissionPresenter::class);
}

// ── expectedOutcome ──────────────────────────────────────────────────────────

it('derives the corrective outcome differently when the equipment is stopped', function () {
    $stopped = WorkOrder::factory()->make(['work_order_type' => WorkOrderType::Corrective, 'equipment_stopped' => true]);
    $running = WorkOrder::factory()->make(['work_order_type' => WorkOrderType::Corrective, 'equipment_stopped' => false]);

    expect(presenter()->expectedOutcome($stopped))->toBe('Restablecer la operación del equipo')
        ->and(presenter()->expectedOutcome($running))->toBe('Eliminar la falla antes de que detenga el equipo');
});

it('derives a fixed outcome phrase for each work order type', function (WorkOrderType $type, string $expected) {
    $wo = WorkOrder::factory()->make(['work_order_type' => $type]);

    expect(presenter()->expectedOutcome($wo))->toBe($expected);
})->with([
    [WorkOrderType::Preventive, 'Mantener la disponibilidad y prevenir fallas'],
    [WorkOrderType::Predictive, 'Confirmar el estado real del equipo antes de que falle'],
    [WorkOrderType::Improvement, 'Aumentar la confiabilidad o el desempeño del equipo'],
    [WorkOrderType::Emergency, 'Recuperar la operación segura en el menor tiempo posible'],
]);

// ── progress ──────────────────────────────────────────────────────────────────

it('progress places a draft work order at the first stage with 0%', function () {
    $wo = WorkOrder::factory()->create(['status' => WorkOrderStatus::Draft]);

    $progress = presenter()->progress($wo);

    expect($progress['percentage'])->toBe(0)
        ->and($progress['current_status'])->toBe('draft')
        ->and($progress['stages'][0]['current'])->toBeTrue()
        ->and($progress['stages'][0]['done'])->toBeFalse()
        ->and($progress['off_spine'])->toBeNull();
});

it('progress marks every prior stage done and reaches 100% when closed', function () {
    $wo = WorkOrder::factory()->create(['status' => WorkOrderStatus::Closed]);

    $progress = presenter()->progress($wo);

    expect($progress['percentage'])->toBe(100);
    foreach ($progress['stages'] as $stage) {
        expect($stage['done'])->toBeTrue();
    }
});

it('progress treats On Hold as a pause inside In Progress, not a stage of its own', function () {
    $wo = WorkOrder::factory()->create(['status' => WorkOrderStatus::OnHold]);

    $progress = presenter()->progress($wo);

    $inProgressStage = collect($progress['stages'])->firstWhere('status', 'in_progress');

    expect($inProgressStage['current'])->toBeTrue()
        ->and($progress['off_spine'])->toBe(['status' => 'on_hold', 'label' => 'En Espera']);
});

it('progress freezes a cancelled work order at the last stage it actually reached', function () {
    $wo = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::Cancelled,
        'started_at' => now()->subDay(),
    ]);

    $progress = presenter()->progress($wo);

    $inProgressStage = collect($progress['stages'])->firstWhere('status', 'in_progress');

    // Frozen at In Progress (started_at is set) — not marked "current" since
    // the work order didn't arrive there normally, and never claims Completed.
    expect($inProgressStage['done'])->toBeFalse()
        ->and($progress['off_spine']['status'])->toBe('cancelled')
        ->and(collect($progress['stages'])->firstWhere('status', 'completed')['done'])->toBeFalse();
});

// ── previousIntervention ────────────────────────────────────────────────────

it('previousIntervention finds the most recent other work order on the same equipment', function () {
    $equipment = Equipment::factory()->create();
    $older = WorkOrder::factory()->create(['equipment_id' => $equipment->id, 'created_at' => now()->subDays(20)]);
    $current = WorkOrder::factory()->create(['equipment_id' => $equipment->id, 'created_at' => now()]);

    $result = presenter()->previousIntervention($current);

    expect($result)->not->toBeNull()
        ->and($result['work_order_number'])->toBe($older->work_order_number);
});

it('previousIntervention returns null when this is the only work order on the equipment', function () {
    $wo = WorkOrder::factory()->create();

    expect(presenter()->previousIntervention($wo))->toBeNull();
});

// ── origin ───────────────────────────────────────────────────────────────────

it('origin reports the maintenance request when the work order was reported', function () {
    $request = MaintenanceRequest::factory()->create(['title' => 'Vibración anormal en el eje']);
    $wo = WorkOrder::factory()->create(['maintenance_request_id' => $request->id]);
    $wo->load('maintenanceRequest');

    $origin = presenter()->origin($wo);

    expect($origin['type'])->toBe('request')
        ->and($origin['description'])->toBe('Vibración anormal en el eje');
});

it('origin reports the maintenance plan when the work order was generated preventively', function () {
    $plan = MaintenancePlan::factory()->create(['name' => 'Mantenimiento preventivo mensual']);
    $wo = WorkOrder::factory()->create(['maintenance_plan_id' => $plan->id]);
    $wo->load('maintenancePlan');

    $origin = presenter()->origin($wo);

    expect($origin['type'])->toBe('plan')
        ->and($origin['description'])->toBe('Mantenimiento preventivo mensual');
});

it('origin returns null when the work order was created directly with no linked source', function () {
    $wo = WorkOrder::factory()->create();
    $wo->load('maintenanceRequest', 'maintenancePlan');

    expect(presenter()->origin($wo))->toBeNull();
});

// ── checklist ────────────────────────────────────────────────────────────────

it('checklist returns the linked plan\'s tasks in order', function () {
    $plan = MaintenancePlan::factory()->create();
    MaintenancePlanTask::factory()->create(['maintenance_plan_id' => $plan->id, 'sort_order' => 1, 'title' => 'Verificar torque']);
    MaintenancePlanTask::factory()->create(['maintenance_plan_id' => $plan->id, 'sort_order' => 2, 'title' => 'Cambiar filtro']);

    $wo = WorkOrder::factory()->create(['maintenance_plan_id' => $plan->id]);
    $wo->load('maintenancePlan.tasks');

    $checklist = presenter()->checklist($wo);

    expect($checklist)->toHaveCount(2)
        ->and($checklist[0]['title'])->toBe('Verificar torque');
});

it('checklist returns an empty array when the work order has no plan', function () {
    $wo = WorkOrder::factory()->create();
    $wo->load('maintenancePlan');

    expect(presenter()->checklist($wo))->toBe([]);
});

// ── completionReadiness ─────────────────────────────────────────────────────

it('completionReadiness blocks on missing result and missing technician signature', function () {
    $wo = WorkOrder::factory()->create(['work_performed' => null]);
    $wo->load(['attachments', 'signatures']);

    $readiness = collect(presenter()->completionReadiness($wo))->keyBy('key');

    expect($readiness['result']['satisfied'])->toBeFalse()
        ->and($readiness['result']['blocking'])->toBeTrue()
        ->and($readiness['signature']['satisfied'])->toBeFalse()
        ->and($readiness['signature']['blocking'])->toBeTrue()
        ->and($readiness['evidence']['blocking'])->toBeFalse();
});

it('completionReadiness is satisfied once work_performed and a technician signature exist', function () {
    $wo = WorkOrder::factory()->create(['work_performed' => 'Se cambió el rodamiento.']);
    WorkOrderSignature::factory()->create(['work_order_id' => $wo->id, 'tenant_id' => $wo->tenant_id]);
    $wo->load(['attachments', 'signatures']);

    $readiness = collect(presenter()->completionReadiness($wo))->keyBy('key');

    expect($readiness['result']['satisfied'])->toBeTrue()
        ->and($readiness['signature']['satisfied'])->toBeTrue();
});

it('completionReadiness does not count a supervisor-only signature as the technician signature', function () {
    $wo = WorkOrder::factory()->create();
    WorkOrderSignature::factory()->supervisor()->create(['work_order_id' => $wo->id, 'tenant_id' => $wo->tenant_id]);
    $wo->load(['attachments', 'signatures']);

    $readiness = collect(presenter()->completionReadiness($wo))->keyBy('key');

    expect($readiness['signature']['satisfied'])->toBeFalse();
});

it('completionReadiness flags evidence as satisfied once at least one attachment exists', function () {
    $wo = WorkOrder::factory()->create();
    WorkOrderAttachment::factory()->create(['work_order_id' => $wo->id, 'tenant_id' => $wo->tenant_id]);
    $wo->load(['attachments', 'signatures']);

    $readiness = collect(presenter()->completionReadiness($wo))->keyBy('key');

    expect($readiness['evidence']['satisfied'])->toBeTrue();
});

// ── completionSummary ───────────────────────────────────────────────────────

it('completionSummary reports equipment, time, evidence count and expected outcome', function () {
    $equipment = Equipment::factory()->create(['name' => 'Bomba BC-001']);
    $wo = WorkOrder::factory()->create(['equipment_id' => $equipment->id, 'actual_labor_hours' => 2.25]);
    WorkOrderAttachment::factory()->count(3)->create(['work_order_id' => $wo->id, 'tenant_id' => $wo->tenant_id]);
    $wo->load(['equipment', 'attachments', 'maintenancePlan']);

    $summary = presenter()->completionSummary($wo);

    expect($summary['equipment'])->toBe('Bomba BC-001')
        ->and($summary['time_spent_hours'])->toBe(2.25)
        ->and($summary['evidence_count'])->toBe(3)
        ->and($summary['expected_outcome'])->toBe(presenter()->expectedOutcome($wo));
});
