<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function operationalStaffUser(Tenant $tenant, string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    return $user;
}

it('includes tecnico, supervisor and ingeniero-mantenimiento', function () {
    operationalStaffUser($this->tenant, 'tecnico');
    operationalStaffUser($this->tenant, 'supervisor');
    operationalStaffUser($this->tenant, 'ingeniero-mantenimiento');

    $names = User::query()->operationalStaff()->pluck('name')->all();

    expect($names)->toHaveCount(3);
});

it('excludes super admin and administrative-only roles', function () {
    operationalStaffUser($this->tenant, 'administrador-general');
    operationalStaffUser($this->tenant, 'gerencia');
    operationalStaffUser($this->tenant, 'compras');
    operationalStaffUser($this->tenant, 'almacenista');

    // Even a super admin holding an operational role must never appear as assignable staff.
    $superAdmin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
    $superAdmin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $superAdmin->assignRole('tecnico');

    $count = User::query()->operationalStaff()->count();

    expect($count)->toBe(0);
});
