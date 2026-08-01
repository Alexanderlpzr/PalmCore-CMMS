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

function maintenanceRequestUser(Tenant $tenant, ?string $role = null): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($tenant->id);

    if ($role !== null) {
        $user->assignRole($role);
    }

    return $user;
}

/**
 * Rejecting a request is gated on maintenance-requests.review. That used to
 * separate a supervisor from a técnico; with a single tenant role it separates
 * the administrator from a user carrying no role.
 */
it('hides reject from a user without the review permission', function () {
    // Ve la solicitud pero no la revisa: sin al menos el permiso de lectura la
    // página ni siquiera monta, así que no habría acción que ocultar.
    $user = maintenanceRequestUser($this->tenant);
    $user->givePermissionTo('maintenance-requests.view');

    $this->actingAs($user);
    Filament::setTenant($this->tenant);

    Livewire::test(ViewMaintenanceRequest::class, ['record' => $this->request->id])
        ->assertActionHidden('reject');
});

it('shows reject to the tenant administrator', function () {
    $user = maintenanceRequestUser($this->tenant, 'administrador-general');
    $this->actingAs($user);
    Filament::setTenant($this->tenant);

    Livewire::test(ViewMaintenanceRequest::class, ['record' => $this->request->id])
        ->assertActionVisible('reject');
});
