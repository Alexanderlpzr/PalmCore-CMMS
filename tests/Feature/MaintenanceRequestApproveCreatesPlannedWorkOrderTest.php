<?php

use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Filament\Resources\Maintenance\MaintenanceRequest\Pages\ViewMaintenanceRequest;
use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $this->admin->assignRole('administrador-general');

    $this->technician = User::factory()->create(['is_active' => true]);
    $this->technician->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $this->technician->assignRole('tecnico');

    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('creates the work order already planned, skipping the manual Planificar click', function () {
    $request = MaintenanceRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => MaintenanceRequestStatus::UnderReview,
        'request_type' => 'corrective',
    ]);

    Livewire::test(ViewMaintenanceRequest::class, ['record' => $request->id])
        ->callAction('approve_and_create_wo', data: [
            'technician_ids' => [$this->technician->id],
            'work_order_type' => 'corrective',
        ])
        ->assertHasNoActionErrors();

    $request->refresh();
    $workOrder = WorkOrder::where('maintenance_request_id', $request->id)->firstOrFail();

    expect($request->status)->toBe(MaintenanceRequestStatus::Converted)
        ->and($workOrder->status)->toBe(WorkOrderStatus::Planned)
        ->and($workOrder->technicians()->where('user_id', $this->technician->id)->exists())->toBeTrue();
});
