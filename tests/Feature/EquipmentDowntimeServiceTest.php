<?php

use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Tenant;
use App\Models\User;

// Nota: los paros ya no salen de las OT (son manuales, ver DowntimeServiceTest).
// Aquí solo queda el estado del equipo, que sí depende del ciclo de la OT.

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

it('restores equipment to active on Completed, before administrative close', function () {
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

    // Equipment is running again the instant the work is completed — no need to
    // wait for Verified/Closed.
    expect($equipment->fresh()->status)->toBe(EquipmentStatus::Active);
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

// ── Los paros ya no salen de las OT ───────────────────────────────────────────

it('una OT con equipo detenido ya NO crea un paro (los paros son manuales)', function () {
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
    $service->transition($wo, WorkOrderStatus::Completed, $user, ['work_performed' => 'ok']);

    // El equipo sí cambia de estado (eso no es un paro), pero no se crea evento de paro.
    expect(EquipmentDowntimeEvent::where('work_order_id', $wo->id)->exists())->toBeFalse();
});
