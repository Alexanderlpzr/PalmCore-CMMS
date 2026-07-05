<?php

use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ListWorkOrders;
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

    $this->technician = User::factory()->create(['is_active' => true]);
    $this->technician->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $this->technician->assignRole('tecnico');

    $this->otherTechnician = User::factory()->create(['is_active' => true]);

    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    $service = app(WorkOrderService::class);

    $this->myWorkOrder = $service->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Asignada al técnico',
        'description' => 'desc',
    ], $this->admin);
    $service->assignTechnician($this->myWorkOrder, $this->technician, TechnicianRole::Technician);

    $this->otherWorkOrder = $service->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Asignada a otro técnico',
        'description' => 'desc',
    ], $this->admin);
    $service->assignTechnician($this->otherWorkOrder, $this->otherTechnician, TechnicianRole::Technician);
});

it('defaults to showing only the technician\'s own work orders', function () {
    $this->actingAs($this->technician);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(ListWorkOrders::class)
        ->assertCanSeeTableRecords([$this->myWorkOrder])
        ->assertCanNotSeeTableRecords([$this->otherWorkOrder]);
});

it('lets the technician turn the filter off to see every work order', function () {
    $this->actingAs($this->technician);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(ListWorkOrders::class)
        ->filterTable('assigned_to_me', false)
        ->assertCanSeeTableRecords([$this->myWorkOrder, $this->otherWorkOrder]);
});

it('does not default the filter on for an administrator', function () {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(ListWorkOrders::class)
        ->assertCanSeeTableRecords([$this->myWorkOrder, $this->otherWorkOrder]);
});
