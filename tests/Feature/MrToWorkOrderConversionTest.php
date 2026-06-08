<?php

use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

// ── MR → WorkOrder conversion ─────────────────────────────────────────────────

it('creates work order from approved maintenance request', function () {
    $service   = app(WorkOrderService::class);
    $tenant    = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user      = User::factory()->create();

    $mr = MaintenanceRequest::factory()->create([
        'tenant_id'    => $tenant->id,
        'equipment_id' => $equipment->id,
        'status'       => MaintenanceRequestStatus::Approved->value,
    ]);

    $wo = $service->createFromMaintenanceRequest($mr, [
        'work_order_type' => 'corrective',
    ], $user);

    expect($wo)->toBeInstanceOf(WorkOrder::class)
        ->and($wo->maintenance_request_id)->toBe($mr->id)
        ->and($wo->equipment_id)->toBe($equipment->id)
        ->and($wo->tenant_id)->toBe($tenant->id)
        ->and($wo->status)->toBe(WorkOrderStatus::Draft);
});

it('marks maintenance request as converted after WO creation', function () {
    $service   = app(WorkOrderService::class);
    $tenant    = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user      = User::factory()->create();

    $mr = MaintenanceRequest::factory()->create([
        'tenant_id'    => $tenant->id,
        'equipment_id' => $equipment->id,
        'status'       => MaintenanceRequestStatus::Approved->value,
    ]);

    $service->createFromMaintenanceRequest($mr, ['work_order_type' => 'corrective'], $user);
    $mr->refresh();

    expect($mr->status)->toBe(MaintenanceRequestStatus::Converted);
});

it('links mr.work_order_id to the created work order', function () {
    $service   = app(WorkOrderService::class);
    $tenant    = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user      = User::factory()->create();

    $mr = MaintenanceRequest::factory()->create([
        'tenant_id'    => $tenant->id,
        'equipment_id' => $equipment->id,
        'status'       => MaintenanceRequestStatus::Approved->value,
    ]);

    $wo = $service->createFromMaintenanceRequest($mr, ['work_order_type' => 'corrective'], $user);
    $mr->refresh();

    expect($mr->work_order_id)->toBe($wo->id);
});

it('inherits title and description from maintenance request', function () {
    $service   = app(WorkOrderService::class);
    $tenant    = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user      = User::factory()->create();

    $mr = MaintenanceRequest::factory()->create([
        'tenant_id'    => $tenant->id,
        'equipment_id' => $equipment->id,
        'status'       => MaintenanceRequestStatus::Approved->value,
        'title'        => 'Falla en compresor principal',
        'description'  => 'El compresor pierde presión.',
    ]);

    $wo = $service->createFromMaintenanceRequest($mr, ['work_order_type' => 'corrective'], $user);

    expect($wo->title)->toBe('Falla en compresor principal')
        ->and($wo->description)->toBe('El compresor pierde presión.');
});

it('generates a valid OT number on conversion', function () {
    $service   = app(WorkOrderService::class);
    $tenant    = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => 'COMP-01']);
    $user      = User::factory()->create();

    $mr = MaintenanceRequest::factory()->create([
        'tenant_id'    => $tenant->id,
        'equipment_id' => $equipment->id,
        'status'       => MaintenanceRequestStatus::Approved->value,
    ]);

    $wo = $service->createFromMaintenanceRequest($mr, ['work_order_type' => 'corrective'], $user);

    expect($wo->work_order_number)->toStartWith('OT-'.date('Y').'-COMP-01-');
});

it('emergency conversion starts work order in in_progress', function () {
    $service   = app(WorkOrderService::class);
    $tenant    = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user      = User::factory()->create();

    $mr = MaintenanceRequest::factory()->create([
        'tenant_id'    => $tenant->id,
        'equipment_id' => $equipment->id,
        'status'       => MaintenanceRequestStatus::Approved->value,
    ]);

    $wo = $service->createFromMaintenanceRequest($mr, ['work_order_type' => 'emergency'], $user);

    expect($wo->status)->toBe(WorkOrderStatus::InProgress)
        ->and($wo->started_at)->not->toBeNull();
});
