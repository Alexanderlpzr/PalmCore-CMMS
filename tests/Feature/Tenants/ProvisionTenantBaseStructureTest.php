<?php

use App\Actions\Tenants\ProvisionTenantBaseStructure;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\Permission;
use App\Models\Plant;
use App\Models\Role;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    // Roles sync global permissions, which PermissionSeeder creates. In production
    // these already exist from the initial seed; the test must recreate them.
    $this->seed(PermissionSeeder::class);
});

it('provisions a default plant named after the tenant itself', function () {
    $tenant = Tenant::factory()->create(['name' => 'Extractora El Pajuil']);

    app(ProvisionTenantBaseStructure::class)->handle($tenant);

    $plant = Plant::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

    expect($plant)->not->toBeNull()
        ->and($plant->code)->toBe('PLT-01')
        ->and($plant->name)->toBe('Extractora El Pajuil')
        ->and($plant->is_active)->toBeTrue();
});

it('provisions the nine process-flow areas for a new tenant', function () {
    $tenant = Tenant::factory()->create();

    app(ProvisionTenantBaseStructure::class)->handle($tenant);

    $areas = Area::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();

    expect($areas)->toHaveCount(9)
        ->and($areas->pluck('code')->all())->toEqualCanonicalizing([
            'REC-01', 'EST-01', 'DFR-01', 'RAQ-01', 'EXT-01', 'CLA-01', 'PAL-01', 'DFB-01', 'COG-01',
        ]);
});

it('provisions the base equipment inventory for a new tenant', function () {
    $tenant = Tenant::factory()->create();

    app(ProvisionTenantBaseStructure::class)->handle($tenant);

    expect(Equipment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(99)
        ->and(EquipmentComponent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(461);
});

it('provisions the single per-tenant administrator role', function () {
    $tenant = Tenant::factory()->create();

    app(ProvisionTenantBaseStructure::class)->handle($tenant);

    $roles = Role::where('team_id', $tenant->id)->pluck('name')->all();

    expect($roles)->toEqualCanonicalizing(['administrador-general']);
});

it('self-heals a missing permission catalogue instead of throwing', function () {
    // Simulate a DB where the permission catalogue has not been seeded yet
    // (e.g. the rollout migration has not run). Provisioning must seed the
    // permissions itself so syncPermissions() never hits PermissionDoesNotExist.
    Permission::query()->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $tenant = Tenant::factory()->create();

    app(ProvisionTenantBaseStructure::class)->handle($tenant);

    setPermissionsTeamId($tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $admin = Role::where('team_id', $tenant->id)->where('name', 'administrador-general')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->hasPermissionTo('announcements.view'))->toBeTrue()
        ->and($admin->hasPermissionTo('carousel-slides.view'))->toBeTrue();
});

it('is idempotent — re-running does not duplicate structure', function () {
    $tenant = Tenant::factory()->create();
    $action = app(ProvisionTenantBaseStructure::class);

    $action->handle($tenant);
    $action->handle($tenant);

    expect(Plant::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(Area::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(9)
        ->and(Equipment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(99)
        ->and(EquipmentComponent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(461)
        ->and(Role::where('team_id', $tenant->id)->count())->toBe(1);
});
