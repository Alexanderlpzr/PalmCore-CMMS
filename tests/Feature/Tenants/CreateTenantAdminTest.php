<?php

use App\Actions\Tenants\CreateTenantAdmin;
use App\Actions\Tenants\ProvisionTenantBaseStructure;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    // Roles must exist before an admin can be assigned to them.
    app(ProvisionTenantBaseStructure::class)->handle($this->tenant);
});

it('creates an admin user attached as primary owner of the tenant', function () {
    $user = app(CreateTenantAdmin::class)->handle(
        $this->tenant, 'Ada Lovelace', 'ada@acme.test', 'Admin123'
    );

    expect($user->email)->toBe('ada@acme.test')
        ->and($user->is_super_admin)->toBeFalse()
        ->and(Hash::check('Admin123', $user->password))->toBeTrue();

    $pivot = $user->tenants()->where('tenants.id', $this->tenant->id)->first()->pivot;

    expect((bool) $pivot->is_primary_tenant)->toBeTrue()
        ->and((bool) $pivot->is_owner)->toBeTrue();
});

it('assigns the administrador-general role within the tenant', function () {
    $user = app(CreateTenantAdmin::class)->handle(
        $this->tenant, 'Ada', 'ada@acme.test', 'Admin123'
    );

    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($user->fresh()->hasRole('administrador-general'))->toBeTrue();
});

it('reuses an existing user instead of duplicating', function () {
    $existing = User::factory()->create(['email' => 'ada@acme.test']);

    $user = app(CreateTenantAdmin::class)->handle(
        $this->tenant, 'Ada', 'ada@acme.test', 'Admin123'
    );

    expect($user->id)->toBe($existing->id)
        ->and(User::where('email', 'ada@acme.test')->count())->toBe(1);
});
