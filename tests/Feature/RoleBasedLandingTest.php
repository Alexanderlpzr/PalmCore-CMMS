<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Inicio;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
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
function actAsLanding(Tenant $tenant, ?string $role = null): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($tenant->id);

    if ($role !== null) {
        $user->assignRole($role);
    }

    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($tenant);

    return $user;
}

/**
 * The maintenance engineer now runs the tenant as administrador-general, so the
 * Dashboard landing that used to belong to ingeniero-mantenimiento/supervisor
 * follows him into that role.
 */
it('redirects the tenant administrator landing on Inicio to the Dashboard', function () {
    actAsLanding($this->tenant, 'administrador-general');

    Livewire::test(Inicio::class)
        ->assertRedirect(Dashboard::getUrl());
});

it('does NOT redirect a user without the administrator role', function () {
    actAsLanding($this->tenant);

    Livewire::test(Inicio::class)
        ->assertNoRedirect();
});

it('redirects only once per session, honoring later visits to Inicio', function () {
    actAsLanding($this->tenant, 'administrador-general');

    // First landing bounces to the Dashboard...
    Livewire::test(Inicio::class)->assertRedirect(Dashboard::getUrl());

    // ...but a deliberate return to Inicio afterwards is honored.
    Livewire::test(Inicio::class)->assertNoRedirect();
});

it('draws the maintenance portal with cards that unfold their cases and the rest in tabs', function () {
    Cache::flush();
    actAsLanding($this->tenant, 'mantenimiento');
    // Ya entró una vez: el salto al Dashboard solo ocurre al iniciar sesión.
    session()->put('landing_redirect_done', true);
    CurrentTenant::set($this->tenant);
    WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'in_progress', 'title' => 'Cambiar rodamiento']);

    Livewire::test(Inicio::class)
        ->assertNoRedirect()
        ->assertSee('Atención requerida')
        ->assertSeeHtml('data-casos="open_work_orders"')
        ->assertSee('Cambiar rodamiento')
        ->assertSee('Acciones rápidas')
        ->assertSee('Noticias y comunicados')
        ->assertSee('Actividad reciente')
        ->assertSeeHtml('data-pestana="actividad"');
});
