<?php

use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Enums\MaintenanceRequestType;
use App\Domain\Maintenance\Services\MaintenanceRequestService;
use App\Models\Equipment;
use App\Models\EquipmentIssueReport;
use App\Models\MaintenanceRequest;
use App\Models\Tenant;
use App\Models\User;

// ── Request number generation ─────────────────────────────────────────────────

it('generates first request number as MR-YYYY-00001', function () {
    $service = app(MaintenanceRequestService::class);
    $tenant  = Tenant::factory()->create();

    $number = $service->generateRequestNumber($tenant->id);

    expect($number)->toBe('MR-'.date('Y').'-00001');
});

it('increments request number sequentially for the same tenant', function () {
    $service  = app(MaintenanceRequestService::class);
    $tenant   = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user     = User::factory()->create();

    $first  = $service->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'request_type' => 'corrective', 'priority' => 'p3_medium', 'title' => 'First', 'description' => 'desc'], $user);
    $second = $service->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'request_type' => 'corrective', 'priority' => 'p3_medium', 'title' => 'Second', 'description' => 'desc'], $user);

    expect($first->request_number)->toBe('MR-'.date('Y').'-00001')
        ->and($second->request_number)->toBe('MR-'.date('Y').'-00002');
});

it('does not share sequences between tenants', function () {
    $service   = app(MaintenanceRequestService::class);
    $tenantA   = Tenant::factory()->create();
    $tenantB   = Tenant::factory()->create();
    $equipA    = Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    $equipB    = Equipment::factory()->create(['tenant_id' => $tenantB->id]);
    $user      = User::factory()->create();

    $mrA = $service->create(['tenant_id' => $tenantA->id, 'equipment_id' => $equipA->id, 'request_type' => 'corrective', 'priority' => 'p3_medium', 'title' => 'A', 'description' => 'desc'], $user);
    $mrB = $service->create(['tenant_id' => $tenantB->id, 'equipment_id' => $equipB->id, 'request_type' => 'corrective', 'priority' => 'p3_medium', 'title' => 'B', 'description' => 'desc'], $user);

    expect($mrA->request_number)->toBe('MR-'.date('Y').'-00001')
        ->and($mrB->request_number)->toBe('MR-'.date('Y').'-00001');
});

// ── Create ────────────────────────────────────────────────────────────────────

it('creates a maintenance request with status draft by default', function () {
    $service  = app(MaintenanceRequestService::class);
    $tenant   = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user     = User::factory()->create();

    $mr = $service->create([
        'tenant_id'    => $tenant->id,
        'equipment_id' => $equipment->id,
        'request_type' => MaintenanceRequestType::Corrective->value,
        'priority'     => 'p3_medium',
        'title'        => 'Falla en compresor',
        'description'  => 'El compresor hace ruido.',
    ], $user);

    expect($mr->status)->toBe(MaintenanceRequestStatus::Draft)
        ->and($mr->created_by)->toBe($user->id);
});

it('creates emergency request with status under_review directly', function () {
    $service  = app(MaintenanceRequestService::class);
    $tenant   = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user     = User::factory()->create();

    $mr = $service->create([
        'tenant_id'    => $tenant->id,
        'equipment_id' => $equipment->id,
        'request_type' => MaintenanceRequestType::Emergency->value,
        'priority'     => 'p1_critical',
        'title'        => 'Derrame de aceite crítico',
        'description'  => 'Emergencia en planta.',
    ], $user);

    expect($mr->status)->toBe(MaintenanceRequestStatus::UnderReview);
});

// ── Transitions ───────────────────────────────────────────────────────────────

it('transitions request from draft to submitted', function () {
    $service = app(MaintenanceRequestService::class);
    $user    = User::factory()->create();
    $mr      = MaintenanceRequest::factory()->create();

    $service->transition($mr, MaintenanceRequestStatus::Submitted, $user);
    $mr->refresh();

    expect($mr->status)->toBe(MaintenanceRequestStatus::Submitted)
        ->and($mr->submitted_at)->not->toBeNull();
});

it('throws when transition is invalid', function () {
    $service = app(MaintenanceRequestService::class);
    $user    = User::factory()->create();
    $mr      = MaintenanceRequest::factory()->create();

    expect(fn () => $service->transition($mr, MaintenanceRequestStatus::Approved, $user))
        ->toThrow(\RuntimeException::class);
});
