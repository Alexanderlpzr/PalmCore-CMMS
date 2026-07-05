<?php

use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Enums\WorkOrderSignatureType;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ViewWorkOrder;
use App\Filament\Resources\Maintenance\WorkOrder\RelationManagers\SignaturesRelationManager;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

// A well-known 1x1 transparent PNG, base64-encoded, mimicking what the canvas signature pad sends.
const FAKE_SIGNATURE_DATA_URL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

beforeEach(function () {
    Storage::fake('work_orders_private');

    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
});

function signaturePadUser(Tenant $tenant, string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    return $user;
}

it('stores a decoded PNG from a data URL as the signature image', function () {
    $tech = signaturePadUser($this->tenant, 'tecnico');
    $service = app(WorkOrderService::class);

    $wo = $service->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Test',
        'description' => 'desc',
    ], $tech);

    $signature = $service->addSignature(
        $wo,
        $tech,
        WorkOrderSignatureType::TechnicianCompletion,
        null,
        null,
        FAKE_SIGNATURE_DATA_URL,
    );

    expect($signature->image_path)->not->toBeNull();
    Storage::disk('work_orders_private')->assertExists($signature->image_path);
});

it('requires a drawn signature to complete a work order from the Filament action', function () {
    $tech = signaturePadUser($this->tenant, 'tecnico');
    $service = app(WorkOrderService::class);

    $wo = $service->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Test',
        'description' => 'desc',
    ], $tech);
    $service->assignTechnician($wo, $tech, TechnicianRole::Technician);
    $service->transition($wo, WorkOrderStatus::Planned, $tech);
    $service->transition($wo, WorkOrderStatus::InProgress, $tech);

    $this->actingAs($tech);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(ViewWorkOrder::class, ['record' => $wo->id])
        ->callAction('complete', data: ['work_performed' => 'Listo'])
        ->assertHasActionErrors(['signature']);

    Livewire::test(ViewWorkOrder::class, ['record' => $wo->id])
        ->callAction('complete', data: [
            'work_performed' => 'Listo',
            'signature' => FAKE_SIGNATURE_DATA_URL,
        ])
        ->assertHasNoActionErrors();

    $signature = $wo->fresh()->signatures()
        ->where('signature_type', WorkOrderSignatureType::TechnicianCompletion->value)
        ->first();

    expect($wo->fresh()->status)->toBe(WorkOrderStatus::Completed)
        ->and($signature->image_path)->not->toBeNull();
});

it('hides the manual create-signature action from the relation manager', function () {
    $admin = signaturePadUser($this->tenant, 'administrador-general');
    $service = app(WorkOrderService::class);

    $wo = $service->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Test',
        'description' => 'desc',
    ], $admin);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(SignaturesRelationManager::class, [
        'ownerRecord' => $wo,
        'pageClass' => ViewWorkOrder::class,
    ])->assertActionDoesNotExist(TestAction::make('create')->table());
});
