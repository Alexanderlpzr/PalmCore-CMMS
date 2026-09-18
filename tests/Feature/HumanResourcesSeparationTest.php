<?php

use App\Filament\Pages\Inicio;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * Mantenimiento y talento humano, cada uno en lo suyo.
 *
 * La cuenta de RRHH ve Personal, horas y nómina, y nada de la planta. El administrador
 * del tenant ve la planta y nada de la nómina. Se prueba recorriendo todas las pantallas
 * del panel y no una lista escrita a mano: el fallo que esto vino a cerrar fueron siete
 * pantallas de indicadores que no preguntaban nada, y una pantalla nueva que olvide
 * preguntar tiene que romper este test.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function actingWithTenantRole(string $role, Tenant $tenant): User
{
    $user = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);
    $user = $user->fresh();

    test()->actingAs($user);
    Filament::setTenant($tenant);

    return $user;
}

/**
 * Los grupos de menú de cada pantalla a la que el usuario actual puede entrar.
 *
 * @return list<string>
 */
function accessibleNavigationGroups(): array
{
    $panel = Filament::getPanel('admin');
    $groups = [];

    foreach ([...$panel->getResources(), ...$panel->getPages()] as $screen) {
        if ($screen::canAccess()) {
            $groups[] = (string) ($screen::getNavigationGroup() ?? 'Sin grupo').' / '.class_basename($screen);
        }
    }

    return $groups;
}

it('talento humano solo entra a las pantallas de Talento Humano', function (): void {
    actingWithTenantRole('talento-humano', $this->tenant);

    $outside = array_values(array_filter(
        accessibleNavigationGroups(),
        // Inicio es la raíz del panel y tiene que dejar entrar para redirigir a Personal.
        fn (string $screen): bool => ! str_starts_with($screen, 'Talento Humano / ')
            && $screen !== 'Sin grupo / Inicio',
    ));

    expect($outside)->toBe([]);
});

it('talento humano sí entra a sus seis pantallas', function (): void {
    actingWithTenantRole('talento-humano', $this->tenant);

    $hr = array_filter(accessibleNavigationGroups(), fn (string $screen): bool => str_starts_with($screen, 'Talento Humano / '));

    expect($hr)->toHaveCount(6);
});

it('el administrador no entra a ninguna pantalla de Talento Humano', function (): void {
    actingWithTenantRole('administrador-general', $this->tenant);

    $hr = array_filter(accessibleNavigationGroups(), fn (string $screen): bool => str_starts_with($screen, 'Talento Humano / '));

    expect($hr)->toBe([]);
});

it('el administrador sigue entrando a lo de mantenimiento', function (): void {
    actingWithTenantRole('administrador-general', $this->tenant);

    expect(accessibleNavigationGroups())
        ->toContain('Gestión de Activos / EquipmentResource')
        ->toContain('Indicadores / Dashboard');
});

it('el administrador no puede asignarse el rol de talento humano', function (): void {
    // La separación sería de escaparate si el administrador pudiera darse el rol. No
    // puede porque Usuarios y Roles son pantallas solo del superadministrador.
    actingWithTenantRole('administrador-general', $this->tenant);

    expect(UserResource::canAccess())->toBeFalse()
        ->and(RoleResource::canAccess())->toBeFalse();
});

it('talento humano aterriza en Personal y no en el portal de mantenimiento', function (): void {
    actingWithTenantRole('talento-humano', $this->tenant);

    Livewire::test(Inicio::class)->assertRedirect(EmployeeResource::getUrl());

    expect(Inicio::shouldRegisterNavigation())->toBeFalse();
});
