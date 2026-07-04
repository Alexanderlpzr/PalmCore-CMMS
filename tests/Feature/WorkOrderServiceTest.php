<?php

use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Enums\WorkOrderSignatureType;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

// ── Numbering ─────────────────────────────────────────────────────────────────

it('generates first work order number for a new tenant', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => 'EQ001']);

    $number = $service->generateWorkOrderNumber($tenant->id, $equipment->code);

    expect($number)->toBe('OT-'.date('Y').'-EQ001-000001');
});

it('increments sequential per tenant per year', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => 'PRE-001']);
    $user = User::factory()->create();

    $wo1 = $service->create([
        'tenant_id' => $tenant->id, 'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective', 'priority' => 'p3_medium',
        'title' => 'Primera OT', 'description' => 'desc',
    ], $user);

    $wo2 = $service->create([
        'tenant_id' => $tenant->id, 'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective', 'priority' => 'p3_medium',
        'title' => 'Segunda OT', 'description' => 'desc',
    ], $user);

    expect($wo1->work_order_number)->toEndWith('-000001')
        ->and($wo2->work_order_number)->toEndWith('-000002');
});

it('does not share sequences between tenants', function () {
    $service = app(WorkOrderService::class);
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenantA->id, 'code' => 'EQ-A']);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id, 'code' => 'EQ-B']);
    $user = User::factory()->create();

    $woA = $service->create([
        'tenant_id' => $tenantA->id, 'equipment_id' => $equipA->id,
        'work_order_type' => 'corrective', 'priority' => 'p3_medium',
        'title' => 'A', 'description' => 'desc',
    ], $user);

    $woB = $service->create([
        'tenant_id' => $tenantB->id, 'equipment_id' => $equipB->id,
        'work_order_type' => 'corrective', 'priority' => 'p3_medium',
        'title' => 'B', 'description' => 'desc',
    ], $user);

    expect($woA->work_order_number)->toEndWith('-000001')
        ->and($woB->work_order_number)->toEndWith('-000001');
});

// ── Create ────────────────────────────────────────────────────────────────────

it('creates work order with draft status by default', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Falla en bomba',
        'description' => 'desc',
    ], $user);

    expect($wo->status)->toBe(WorkOrderStatus::Draft)
        ->and($wo->created_by)->toBe($user->id)
        ->and($wo->plant_id)->toBe($equipment->plant_id);
});

it('emergency work order starts in progress immediately', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Emergency->value,
        'priority' => 'p1_critical',
        'title' => 'Emergencia',
        'description' => 'desc',
    ], $user);

    expect($wo->status)->toBe(WorkOrderStatus::InProgress)
        ->and($wo->started_at)->not->toBeNull()
        ->and($wo->actual_start_at)->not->toBeNull();
});

// ── Transitions ───────────────────────────────────────────────────────────────

it('transitions draft to planned', function () {
    $service = app(WorkOrderService::class);
    $user = User::factory()->create();
    $wo = WorkOrder::factory()->create();

    $service->assignTechnician($wo, $user, TechnicianRole::Technician);
    $service->transition($wo, WorkOrderStatus::Planned, $user);
    $wo->refresh();

    expect($wo->status)->toBe(WorkOrderStatus::Planned);
});

it('refuses to plan a work order without an assigned technician', function () {
    $service = app(WorkOrderService::class);
    $user = User::factory()->create();
    $wo = WorkOrder::factory()->create();

    expect(fn () => $service->transition($wo, WorkOrderStatus::Planned, $user))
        ->toThrow(RuntimeException::class, 'no tiene técnicos asignados');
});

it('sets started_at when transitioning to in_progress', function () {
    $service = app(WorkOrderService::class);
    $user = User::factory()->create();
    $wo = WorkOrder::factory()->planned()->create();

    $service->transition($wo, WorkOrderStatus::InProgress, $user);
    $wo->refresh();

    expect($wo->status)->toBe(WorkOrderStatus::InProgress)
        ->and($wo->started_at)->not->toBeNull();
});

it('throws when transition is invalid', function () {
    $service = app(WorkOrderService::class);
    $user = User::factory()->create();
    $wo = WorkOrder::factory()->create(); // draft

    expect(fn () => $service->transition($wo, WorkOrderStatus::Closed, $user))
        ->toThrow(RuntimeException::class);
});

it('sets completed_by when transitioning to completed', function () {
    $service = app(WorkOrderService::class);
    $user = User::factory()->create();
    $wo = WorkOrder::factory()->inProgress()->create();

    $service->transition($wo, WorkOrderStatus::Completed, $user, ['work_performed' => 'Trabajo realizado.']);
    $wo->refresh();

    expect($wo->status)->toBe(WorkOrderStatus::Completed)
        ->and($wo->completed_by)->toBe($user->id)
        ->and($wo->completed_at)->not->toBeNull();
});

// ── Technician assignment ─────────────────────────────────────────────────────

it('assigns technician with frozen hourly rate', function () {
    $service = app(WorkOrderService::class);
    $wo = WorkOrder::factory()->create();
    $techUser = User::factory()->create();

    $tech = $service->assignTechnician($wo, $techUser, TechnicianRole::Lead->value, 8.0, 50000.0);

    expect($tech->user_id)->toBe($techUser->id)
        ->and($tech->hourly_rate)->toBe(50000.0)
        ->and($tech->role->value)->toBe('lead');
});

it('updates existing technician on re-assignment', function () {
    $service = app(WorkOrderService::class);
    $wo = WorkOrder::factory()->create();
    $techUser = User::factory()->create();

    $service->assignTechnician($wo, $techUser, TechnicianRole::Helper->value, 4.0, 30000.0);
    $updated = $service->assignTechnician($wo, $techUser, TechnicianRole::Lead->value, 8.0, 45000.0);

    expect($wo->technicians()->count())->toBe(1)
        ->and($updated->role->value)->toBe('lead')
        ->and($updated->hourly_rate)->toBe(45000.0);
});

// ── Time logging ─────────────────────────────────────────────────────────────

it('logs time and updates actual_labor_hours', function () {
    $service = app(WorkOrderService::class);
    $wo = WorkOrder::factory()->create();
    $user = User::factory()->create();
    $start = now()->subHours(3);
    $end = now();

    $service->logTime($wo, $user, $start, $end, 'Revisión general');
    $wo->refresh();

    expect($wo->actual_labor_hours)->toBe(3.0);
});

it('logs open time session with null ended_at', function () {
    $service = app(WorkOrderService::class);
    $wo = WorkOrder::factory()->create();
    $user = User::factory()->create();

    $log = $service->logTime($wo, $user, now(), null);

    expect($log->isOpen())->toBeTrue()
        ->and($log->hours)->toBeNull();
});

// ── Costs ─────────────────────────────────────────────────────────────────────

it('recalculates costs from time logs and parts', function () {
    $service = app(WorkOrderService::class);
    $wo = WorkOrder::factory()->create();
    $techUser = User::factory()->create();

    $service->assignTechnician($wo, $techUser, TechnicianRole::Technician->value, 8.0, 50000.0);
    $service->logTime($wo, $techUser, now()->subHours(2), now());

    $wo->parts()->create([
        'tenant_id' => $wo->tenant_id,
        'part_code' => 'FIL-001',
        'description' => 'Filtro',
        'quantity' => 2,
        'unit' => 'pcs',
        'unit_cost' => 15000,
        'total_cost' => 30000,
    ]);

    $service->recalculateCosts($wo);
    $wo->refresh();

    expect($wo->actual_cost_labor)->toBe(100000.0)
        ->and($wo->actual_cost_parts)->toBe(30000.0)
        ->and($wo->actual_cost_total)->toBe(130000.0);
});

// ── Signatures ────────────────────────────────────────────────────────────────

it('adds technician completion signature', function () {
    $service = app(WorkOrderService::class);
    $wo = WorkOrder::factory()->create();
    $user = User::factory()->create();

    $sig = $service->addSignature($wo, $user, WorkOrderSignatureType::TechnicianCompletion, 'Todo en orden');

    expect($sig->user_id)->toBe($user->id)
        ->and($sig->signature_type)->toBe(WorkOrderSignatureType::TechnicianCompletion)
        ->and($sig->notes)->toBe('Todo en orden');
});

it('updates existing signature instead of creating duplicate', function () {
    $service = app(WorkOrderService::class);
    $wo = WorkOrder::factory()->create();
    $user = User::factory()->create();

    $service->addSignature($wo, $user, WorkOrderSignatureType::TechnicianCompletion, 'Primera');
    $service->addSignature($wo, $user, WorkOrderSignatureType::TechnicianCompletion, 'Actualizada');

    expect($wo->signatures()->count())->toBe(1)
        ->and($wo->signatures()->first()->notes)->toBe('Actualizada');
});
