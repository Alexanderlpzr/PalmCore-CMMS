<?php

use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Filament\Resources\Maintenance\MaintenanceRequest\Pages\ViewMaintenanceRequest;
use App\Models\Equipment;
use App\Models\MaintenanceRequest;
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

    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->request = MaintenanceRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'status' => MaintenanceRequestStatus::UnderReview,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function maintenanceRequestUser(Tenant $tenant, string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    return $user;
}

it('hides reject from a técnico', function () {
    $user = maintenanceRequestUser($this->tenant, 'tecnico');
    $this->actingAs($user);
    Filament::setTenant($this->tenant);

    Livewire::test(ViewMaintenanceRequest::class, ['record' => $this->request->id])
        ->assertActionHidden('reject');
});

it('shows reject to a supervisor', function () {
    $user = maintenanceRequestUser($this->tenant, 'supervisor');
    $this->actingAs($user);
    Filament::setTenant($this->tenant);

    Livewire::test(ViewMaintenanceRequest::class, ['record' => $this->request->id])
        ->assertActionVisible('reject');
});
