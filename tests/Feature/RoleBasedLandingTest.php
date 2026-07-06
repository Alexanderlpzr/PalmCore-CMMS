<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Inicio;
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
});

/** Create a user with the given role, sign them in, and resolve panel + tenant. */
function actAsLanding(Tenant $tenant, string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($tenant);

    return $user;
}

it('redirects a maintenance engineer landing on Inicio to the Dashboard', function () {
    actAsLanding($this->tenant, 'ingeniero-mantenimiento');

    Livewire::test(Inicio::class)
        ->assertRedirect(Dashboard::getUrl());
});

it('redirects a supervisor landing on Inicio to the Dashboard', function () {
    actAsLanding($this->tenant, 'supervisor');

    Livewire::test(Inicio::class)
        ->assertRedirect(Dashboard::getUrl());
});

it('does NOT redirect an administrator — they keep the corporate portal as home', function () {
    actAsLanding($this->tenant, 'administrador-general');

    Livewire::test(Inicio::class)
        ->assertNoRedirect();
});

it('redirects the engineer only once per session, honoring later visits to Inicio', function () {
    actAsLanding($this->tenant, 'ingeniero-mantenimiento');

    // First landing bounces to the Dashboard...
    Livewire::test(Inicio::class)->assertRedirect(Dashboard::getUrl());

    // ...but a deliberate return to Inicio afterwards is honored.
    Livewire::test(Inicio::class)->assertNoRedirect();
});
