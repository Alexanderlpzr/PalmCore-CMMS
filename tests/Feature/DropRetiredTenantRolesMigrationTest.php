<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * The migration deletes production data across every tenant, and no other test
 * exercises its delete path — the suite runs it against an empty roles table,
 * where it returns early. This rebuilds the pre-migration state and runs it.
 */
$retiredRoles = [
    'gerencia',
    'plant-manager',
    'ingeniero-mantenimiento',
    'supervisor',
    'tecnico',
    'almacenista',
    'compras',
    'operario',
];

function runDropRetiredRolesMigration(): void
{
    $migration = require database_path('migrations/2026_08_01_064130_drop_retired_tenant_roles.php');
    $migration->up();
}

beforeEach(function () use ($retiredRoles) {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Reconstruye la matriz de nueve roles tal como estaba antes del cambio.
    foreach ([...$retiredRoles, 'administrador-general'] as $name) {
        Role::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
            'team_id' => $this->tenant->id,
        ]);
    }
});

it('deletes every retired role and keeps administrador-general', function () use ($retiredRoles) {
    runDropRetiredRolesMigration();

    $remaining = Role::where('team_id', $this->tenant->id)->pluck('name')->all();

    expect($remaining)->toEqualCanonicalizing(['administrador-general'])
        ->and(Role::whereIn('name', $retiredRoles)->count())->toBe(0);
});

it('strips the retired role from users without touching administrators', function () {
    $tecnico = User::factory()->create(['is_active' => true]);
    $tecnico->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $tecnico->assignRole('tecnico');

    $admin = User::factory()->create(['is_active' => true]);
    $admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $admin->assignRole('administrador-general');

    runDropRetiredRolesMigration();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($tecnico->fresh()->roles)->toBeEmpty()
        ->and($admin->fresh()->roles->pluck('name')->all())->toBe(['administrador-general']);
});

it('clears every tenant, not just the one in context', function () use ($retiredRoles) {
    $otherTenant = Tenant::factory()->create();
    setPermissionsTeamId($otherTenant->id);

    foreach ($retiredRoles as $name) {
        Role::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
            'team_id' => $otherTenant->id,
        ]);
    }

    runDropRetiredRolesMigration();

    expect(Role::where('team_id', $otherTenant->id)->count())->toBe(0)
        ->and(Role::where('team_id', $this->tenant->id)->pluck('name')->all())
        ->toEqualCanonicalizing(['administrador-general']);
});

it('is idempotent — a second run is a no-op', function () {
    runDropRetiredRolesMigration();
    runDropRetiredRolesMigration();

    expect(Role::where('team_id', $this->tenant->id)->pluck('name')->all())
        ->toEqualCanonicalizing(['administrador-general']);
});
