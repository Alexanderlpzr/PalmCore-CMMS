<?php

use App\Domain\Maintenance\Services\WorkOrderService;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ViewWorkOrder;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
});

function costEditUser(Tenant $tenant, string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    return $user;
}

it('lets an administrator manually override the OT costs', function () {
    $admin = costEditUser($this->tenant, 'administrador-general');
    $wo = app(WorkOrderService::class)->create([
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

    Livewire::test(ViewWorkOrder::class, ['record' => $wo->id])
        ->callAction(TestAction::make('edit_costs'), data: [
            'estimated_cost' => 100000,
            'actual_cost_labor' => 40000,
            'actual_cost_parts' => 25000,
            'actual_cost_external' => 10000,
        ])
        ->assertHasNoActionErrors();

    $wo->refresh();

    expect($wo->estimated_cost)->toBe(100000.0)
        ->and($wo->actual_cost_labor)->toBe(40000.0)
        ->and($wo->actual_cost_parts)->toBe(25000.0)
        ->and($wo->actual_cost_external)->toBe(10000.0)
        ->and($wo->actual_cost_total)->toBe(75000.0);
});

it('hides the cost edit action from a técnico', function () {
    $tech = costEditUser($this->tenant, 'tecnico');
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    $this->actingAs($tech);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(ViewWorkOrder::class, ['record' => $wo->id])
        ->assertActionHidden('edit_costs');
});
