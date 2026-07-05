<?php

use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ViewWorkOrder;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
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

    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('shows the missing-technician banner on a draft WO with no technicians', function () {
    $service = app(WorkOrderService::class);
    $wo = $service->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Sin técnico',
        'description' => 'desc',
    ], $this->admin);

    Livewire::test(ViewWorkOrder::class, ['record' => $wo->id])
        ->assertSee('Falta asignar un técnico');
});

it('hides the banner once a technician is assigned', function () {
    $service = app(WorkOrderService::class);
    $technician = User::factory()->create(['is_active' => true]);

    $wo = $service->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Con técnico',
        'description' => 'desc',
    ], $this->admin);

    $service->assignTechnician($wo, $technician, TechnicianRole::Technician);

    Livewire::test(ViewWorkOrder::class, ['record' => $wo->id])
        ->assertDontSee('Falta asignar un técnico');
});
