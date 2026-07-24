<?php

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\CreateWorkOrder;
use App\Models\Equipment;
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

    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('creates an open (Abierta) work order from the form without requiring a técnico', function () {
    Livewire::test(CreateWorkOrder::class)
        ->fillForm([
            'equipment_id' => $this->equipment->id,
            'work_order_type' => 'corrective',
            'priority' => 'p3_medium',
            'title' => 'OT sin técnico',
            'description' => 'Trabajo de prueba',
            'executed_by' => 'El mecánico y su auxiliar',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $wo = WorkOrder::where('title', 'OT sin técnico')->firstOrFail();

    expect($wo->status)->toBe(WorkOrderStatus::Draft)
        ->and($wo->executed_by)->toBe('El mecánico y su auxiliar')
        ->and($wo->technicians()->count())->toBe(0);
});
