<?php

use App\Domain\Assets\Enums\EquipmentDowntimeCauseType;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Tenant;
use App\Models\User;

// ── Equipment status auto-transition ─────────────────────────────────────────

it('sets equipment to under_maintenance when WO goes InProgress with equipment_stopped', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'status' => EquipmentStatus::Active]);
    $user = User::factory()->create();

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Falla en bomba',
        'description' => 'desc',
        'equipment_stopped' => true,
    ], $user);

    $service->assignTechnician($wo, $user, TechnicianRole::Technician);
    $service->transition($wo, WorkOrderStatus::Planned, $user);
    $service->transition($wo, WorkOrderStatus::InProgress, $user);

    expect($equipment->fresh()->status)->toBe(EquipmentStatus::UnderMaintenance);
});

it('does NOT change equipment status when equipment_stopped is false', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'status' => EquipmentStatus::Active]);
    $user = User::factory()->create();

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Inspección',
        'description' => 'desc',
        'equipment_stopped' => false,
    ], $user);

    $service->assignTechnician($wo, $user, TechnicianRole::Technician);
    $service->transition($wo, WorkOrderStatus::Planned, $user);
    $service->transition($wo, WorkOrderStatus::InProgress, $user);

    expect($equipment->fresh()->status)->toBe(EquipmentStatus::Active);
});

it('restores equipment to active when WO is Closed and no other stopped WOs exist', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'status' => EquipmentStatus::Active]);
    $user = User::factory()->create();

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Falla',
        'description' => 'desc',
        'equipment_stopped' => true,
    ], $user);

    $service->assignTechnician($wo, $user, TechnicianRole::Technician);
    $service->transition($wo, WorkOrderStatus::Planned, $user);
    $service->transition($wo, WorkOrderStatus::InProgress, $user);
    $service->transition($wo, WorkOrderStatus::Completed, $user, ['work_performed' => 'reparado']);
    $service->transition($wo, WorkOrderStatus::Verified, $user);
    $service->transition($wo, WorkOrderStatus::Closed, $user);

    expect($equipment->fresh()->status)->toBe(EquipmentStatus::Active);
});

it('restores equipment to active when WO is Cancelled', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'status' => EquipmentStatus::Active]);
    $user = User::factory()->create();

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Falla',
        'description' => 'desc',
        'equipment_stopped' => true,
    ], $user);

    $service->assignTechnician($wo, $user, TechnicianRole::Technician);
    $service->transition($wo, WorkOrderStatus::Planned, $user);
    $service->transition($wo, WorkOrderStatus::InProgress, $user);
    $service->transition($wo, WorkOrderStatus::Cancelled, $user);

    expect($equipment->fresh()->status)->toBe(EquipmentStatus::Active);
});

it('keeps equipment under_maintenance when a second stopped WO is still open', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'status' => EquipmentStatus::Active]);
    $user = User::factory()->create();

    $wo1 = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Falla 1',
        'description' => 'desc',
        'equipment_stopped' => true,
    ], $user);

    $wo2 = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Falla 2',
        'description' => 'desc',
        'equipment_stopped' => true,
    ], $user);

    $service->assignTechnician($wo1, $user, TechnicianRole::Technician);
    $service->transition($wo1, WorkOrderStatus::Planned, $user);
    $service->transition($wo1, WorkOrderStatus::InProgress, $user);
    $service->assignTechnician($wo2, $user, TechnicianRole::Technician);
    $service->transition($wo2, WorkOrderStatus::Planned, $user);
    $service->transition($wo2, WorkOrderStatus::InProgress, $user);

    // Close wo1 — wo2 still open, equipment must stay UnderMaintenance
    $service->transition($wo1, WorkOrderStatus::Completed, $user, ['work_performed' => 'parcial']);
    $service->transition($wo1, WorkOrderStatus::Verified, $user);
    $service->transition($wo1, WorkOrderStatus::Closed, $user);

    expect($equipment->fresh()->status)->toBe(EquipmentStatus::UnderMaintenance);
});

// ── Emergency WO ──────────────────────────────────────────────────────────────

it('emergency WO with equipment_stopped triggers equipment sync immediately on create', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'status' => EquipmentStatus::Active]);
    $user = User::factory()->create();

    $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Emergency->value,
        'priority' => 'p1_critical',
        'title' => 'Emergencia crítica',
        'description' => 'desc',
        'equipment_stopped' => true,
    ], $user);

    expect($equipment->fresh()->status)->toBe(EquipmentStatus::UnderMaintenance);
});

// ── Downtime events ───────────────────────────────────────────────────────────

it('creates a downtime event when WO goes InProgress with equipment_stopped', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Falla',
        'description' => 'desc',
        'equipment_stopped' => true,
    ], $user);

    $service->assignTechnician($wo, $user, TechnicianRole::Technician);
    $service->transition($wo, WorkOrderStatus::Planned, $user);
    $service->transition($wo, WorkOrderStatus::InProgress, $user);

    $event = EquipmentDowntimeEvent::where('work_order_id', $wo->id)->first();

    expect($event)->not->toBeNull()
        ->and($event->cause_type)->toBe(EquipmentDowntimeCauseType::Corrective)
        ->and($event->was_planned)->toBeFalse()
        ->and($event->ended_at)->toBeNull()
        ->and($event->work_order_number)->toBe($wo->work_order_number);
});

it('sets was_planned=true for preventive WO downtime event', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Preventive->value,
        'priority' => 'p3_medium',
        'title' => 'Mantenimiento preventivo',
        'description' => 'desc',
        'equipment_stopped' => true,
    ], $user);

    $service->assignTechnician($wo, $user, TechnicianRole::Technician);
    $service->transition($wo, WorkOrderStatus::Planned, $user);
    $service->transition($wo, WorkOrderStatus::InProgress, $user);

    $event = EquipmentDowntimeEvent::where('work_order_id', $wo->id)->first();

    expect($event->cause_type)->toBe(EquipmentDowntimeCauseType::Preventive)
        ->and($event->was_planned)->toBeTrue();
});

it('closes downtime event with duration when WO is Closed', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Falla',
        'description' => 'desc',
        'equipment_stopped' => true,
    ], $user);

    $service->assignTechnician($wo, $user, TechnicianRole::Technician);
    $service->transition($wo, WorkOrderStatus::Planned, $user);
    $service->transition($wo, WorkOrderStatus::InProgress, $user);
    $service->transition($wo, WorkOrderStatus::Completed, $user, ['work_performed' => 'reparado']);
    $service->transition($wo, WorkOrderStatus::Verified, $user);
    $service->transition($wo, WorkOrderStatus::Closed, $user);

    $event = EquipmentDowntimeEvent::where('work_order_id', $wo->id)->first();

    expect($event->ended_at)->not->toBeNull()
        ->and($event->duration_minutes)->toBeGreaterThanOrEqual(0)
        ->and($event->isOngoing())->toBeFalse();
});

it('uses WO downtime_minutes when provided instead of calculated duration', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Falla',
        'description' => 'desc',
        'equipment_stopped' => true,
        'downtime_minutes' => 180,
    ], $user);

    $service->assignTechnician($wo, $user, TechnicianRole::Technician);
    $service->transition($wo, WorkOrderStatus::Planned, $user);
    $service->transition($wo, WorkOrderStatus::InProgress, $user);
    $service->transition($wo, WorkOrderStatus::Completed, $user, ['work_performed' => 'reparado']);
    $service->transition($wo, WorkOrderStatus::Verified, $user);
    $service->transition($wo, WorkOrderStatus::Closed, $user);

    $event = EquipmentDowntimeEvent::where('work_order_id', $wo->id)->first();

    expect($event->duration_minutes)->toBe(180);
});

it('does NOT create duplicate downtime events when WO goes InProgress twice (OnHold → InProgress)', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Falla',
        'description' => 'desc',
        'equipment_stopped' => true,
    ], $user);

    $service->assignTechnician($wo, $user, TechnicianRole::Technician);
    $service->transition($wo, WorkOrderStatus::Planned, $user);
    $service->transition($wo, WorkOrderStatus::InProgress, $user);
    $service->transition($wo, WorkOrderStatus::OnHold, $user);
    $service->transition($wo, WorkOrderStatus::InProgress, $user); // second InProgress

    $count = EquipmentDowntimeEvent::where('work_order_id', $wo->id)->count();

    expect($count)->toBe(1);
});

// ── equipment.last_failure_at ─────────────────────────────────────────────────

it('updates equipment.last_failure_at when downtime event is created', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'last_failure_at' => null]);
    $user = User::factory()->create();

    $wo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Falla',
        'description' => 'desc',
        'equipment_stopped' => true,
    ], $user);

    $service->assignTechnician($wo, $user, TechnicianRole::Technician);
    $service->transition($wo, WorkOrderStatus::Planned, $user);
    $service->transition($wo, WorkOrderStatus::InProgress, $user);

    expect($equipment->fresh()->last_failure_at)->not->toBeNull();
});
