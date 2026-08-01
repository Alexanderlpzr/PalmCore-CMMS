<?php

use App\Infrastructure\Tenancy\CurrentTenant;
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
    CurrentTenant::set($this->tenant);
});

afterEach(function () {
    CurrentTenant::clear();
});

function tenantMember(Tenant $tenant, ?string $role = null, bool $isSuperAdmin = false): User
{
    $user = User::factory()->create([
        'is_active' => true,
        'is_super_admin' => $isSuperAdmin,
    ]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    if ($role !== null) {
        setPermissionsTeamId($tenant->id);
        $user->assignRole($role);
    }

    return $user;
}

it('includes every tenant member regardless of role', function () {
    tenantMember($this->tenant, 'administrador-general');
    tenantMember($this->tenant);
    tenantMember($this->tenant);

    expect(User::query()->operationalStaff()->count())->toBe(3);
});

it('excludes super admins even when they belong to the tenant', function () {
    tenantMember($this->tenant, 'administrador-general', isSuperAdmin: true);

    expect(User::query()->operationalStaff()->count())->toBe(0);
});

/**
 * Tenant isolation used to be a side effect of filtering on the (team-scoped)
 * roles relation. Now that the role filter is gone it is enforced explicitly,
 * so it needs its own test.
 */
it('excludes users belonging to another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    tenantMember($otherTenant);
    tenantMember($this->tenant);

    $ids = User::query()->operationalStaff()->pluck('id')->all();

    expect($ids)->toHaveCount(1);
});
