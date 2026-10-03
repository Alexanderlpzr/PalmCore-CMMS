<?php

use App\Domain\Platform\Enums\HealthStatus;
use App\Domain\Platform\Services\PlatformUserService;
use App\Domain\Platform\Services\SystemHealthService;
use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Filament\Platform\Pages\PlatformDashboard;
use App\Filament\Platform\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Platform\Resources\Tenants\Pages\EditTenant;
use App\Filament\Platform\Resources\Tenants\Pages\ListTenants;
use App\Filament\Platform\Resources\Tenants\Pages\ViewTenant;
use App\Filament\Platform\Resources\Tenants\RelationManagers\UsersRelationManager;
use App\Filament\Platform\Resources\Users\Pages\CreateUser;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/*
 * El panel de plataforma ordenado para quien lo usa: un menú con nombres que no hay
 * que traducir, una sola pantalla por cosa, y ningún botón que haga daño a la vista.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->superAdmin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);

    Filament::setCurrentPanel(Filament::getPanel('platform'));
    $this->actingAs($this->superAdmin);
});

/** Una empresa con sus roles sembrados, como la deja el alta. */
function empresaDePrueba(array $attributes = []): Tenant
{
    $tenant = Tenant::factory()->create($attributes);
    app(TenantRolesSeeder::class)->run($tenant);

    return $tenant;
}

// ── El menú ──────────────────────────────────────────────────────────────────

it('organizes the menu in four groups with plain names', function (): void {
    // Antes: seis grupos para doce secciones, varios con una sola entrada, y nombres
    // como «Observabilidad», «Contenido CMS» o «Dashboard».
    $menu = collect(Filament::getCurrentPanel()->getNavigation())
        ->mapWithKeys(fn (NavigationGroup $group): array => [
            (string) $group->getLabel() => collect($group->getItems())
                ->map(fn (NavigationItem $item): string => (string) $item->getLabel())
                ->values()
                ->all(),
        ])
        ->all();

    expect($menu)->toBe([
        '' => ['Inicio'],
        'Clientes' => ['Empresas', 'Usuarios'],
        'Seguridad' => ['Ingresos', 'Suplantaciones'],
        'Sistema' => ['Tareas en segundo plano', 'Errores', 'Respaldos'],
        'Contenido' => ['Portada del inicio', 'Fondos del login'],
    ]);
});

// ── Empresas ─────────────────────────────────────────────────────────────────

it('creates a company with its administrator on a temporary password', function (): void {
    // Antes, con el campo vacío, el administrador nacía con «Admin123»: la misma clave
    // para cada empresa nueva. Y si se escribía una, quedaba como definitiva.
    Livewire::test(CreateTenant::class)
        ->fillForm([
            'name' => 'Extractora Nueva',
            'slug' => 'extractora-nueva',
            'subscription_plan' => 'starter',
            'subscription_status' => SubscriptionStatus::Active->value,
            'admin_name' => 'Gerente de Planta',
            'admin_email' => 'gerente@nueva.com',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $admin = User::where('email', 'gerente@nueva.com')->sole();

    expect($admin->must_change_password)->toBeTrue()
        ->and(Hash::check('Admin123', $admin->password))->toBeFalse()
        ->and(Tenant::where('slug', 'extractora-nueva')->sole()->timezone)->toBe('America/Bogota');
});

it('gives an existing account access to a new company without touching its password', function (): void {
    $existente = User::factory()->create(['email' => 'ya@existe.com', 'password' => 'Su-Clave-De-Siempre-1']);

    Livewire::test(CreateTenant::class)
        ->fillForm([
            'name' => 'Segunda Extractora',
            'slug' => 'segunda-extractora',
            'subscription_plan' => 'starter',
            'subscription_status' => SubscriptionStatus::Active->value,
            'admin_email' => 'ya@existe.com',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Hash::check('Su-Clave-De-Siempre-1', $existente->refresh()->password))->toBeTrue()
        ->and($existente->must_change_password)->toBeFalse()
        ->and($existente->tenants()->where('slug', 'segunda-extractora')->exists())->toBeTrue();
});

it('closes access when a company is saved as suspended, and reopens it as active', function (): void {
    // El estado y el acceso eran dos campos sueltos: se podía dejar una empresa
    // «Suspendida» que seguía entrando, porque lo que decide el acceso es is_active.
    $tenant = empresaDePrueba(['subscription_status' => SubscriptionStatus::Active, 'is_active' => true]);

    Livewire::test(EditTenant::class, ['record' => $tenant->getRouteKey()])
        ->fillForm(['subscription_status' => SubscriptionStatus::Suspended->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tenant->refresh()->is_active)->toBeFalse();

    Livewire::test(EditTenant::class, ['record' => $tenant->getRouteKey()])
        ->fillForm(['subscription_status' => SubscriptionStatus::Active->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tenant->refresh()->is_active)->toBeTrue();
});

it('saves a company whose country code has three letters', function (): void {
    // La columna es char(3) —COL, ECU, HND— y el formulario pedía dos: ninguna empresa
    // con «COL» se podía guardar, por mucho que solo se le cambiara el teléfono.
    $tenant = empresaDePrueba(['country_code' => 'COL']);

    Livewire::test(EditTenant::class, ['record' => $tenant->getRouteKey()])
        ->fillForm(['contact_phone' => '3001234567'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tenant->refresh()->contact_phone)->toBe('3001234567')
        ->and($tenant->country_code)->toBe('COL');
});

it('never offers to permanently delete a company', function (): void {
    // Archivar se deshace; el borrado definitivo de una empresa entera, no.
    $tenant = empresaDePrueba();

    Livewire::test(EditTenant::class, ['record' => $tenant->getRouteKey()])
        ->assertActionDoesNotExist('forceDelete')
        ->assertActionExists('delete');

    Livewire::test(ListTenants::class)
        ->assertActionDoesNotExist(TestAction::make('forceDelete')->table()->bulk());
});

it('keeps the company actions in a menu that still works', function (): void {
    $tenant = empresaDePrueba(['subscription_status' => SubscriptionStatus::Active, 'is_active' => true]);

    Livewire::test(ListTenants::class)
        ->callAction(TestAction::make('suspend')->table($tenant))
        ->assertNotified('Empresa suspendida');

    expect($tenant->refresh()->subscription_status)->toBe(SubscriptionStatus::Suspended)
        ->and($tenant->is_active)->toBeFalse();
});

it('archives and restores companies from the list in words of archiving, not deleting', function (): void {
    // El botón decía «Archivar seleccionadas» y la confirmación de Filament, «Borrar
    // empresas seleccionados» con un botón «Borrar».
    $tenant = empresaDePrueba();

    Livewire::test(ListTenants::class)
        ->selectTableRecords([$tenant->getKey()])
        ->mountAction(TestAction::make('delete')->table()->bulk())
        ->assertMountedActionModalSee('Archivar las empresas seleccionadas')
        ->assertMountedActionModalDontSee('Borrar')
        ->callMountedAction()
        ->assertNotified('Empresas archivadas');

    expect($tenant->refresh()->trashed())->toBeTrue();

    Livewire::test(ListTenants::class)
        ->filterTable('trashed', false)
        ->selectTableRecords([$tenant->getKey()])
        ->callAction(TestAction::make('restore')->table()->bulk())
        ->assertNotified('Empresas restauradas');

    expect($tenant->refresh()->trashed())->toBeFalse();
});

it('restores an archived company from its own row, where entering as its owner is no longer offered', function (): void {
    $tenant = empresaDePrueba();
    $tenant->users()->attach(User::factory()->create(['is_active' => true])->id, ['is_owner' => true, 'joined_at' => now()]);

    Livewire::test(ListTenants::class)
        ->assertActionVisible(TestAction::make('impersonateOwner')->table($tenant));

    $tenant->delete();

    Livewire::test(ListTenants::class)
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$tenant])
        ->assertActionHidden(TestAction::make('impersonateOwner')->table($tenant))
        ->callAction(TestAction::make('restore')->table($tenant))
        ->assertNotified('Empresa restaurada');

    expect($tenant->refresh()->trashed())->toBeFalse();
});

it('describes suspending as it works: read only, not locked out', function (): void {
    // Decía «Nadie de esta empresa podrá entrar», pero la suspensión deja entrar a
    // consultar y niega crear y cambiar (SubscriptionEnforcementTest).
    $tenant = empresaDePrueba(['subscription_status' => SubscriptionStatus::Active, 'is_active' => true]);

    Livewire::test(ListTenants::class)
        ->mountAction(TestAction::make('suspend')->table($tenant))
        ->assertMountedActionModalSee('entrar a consultar')
        ->assertMountedActionModalDontSee('Nadie de esta empresa podrá entrar');
});

it('shows the access that really applies, even when the stored status does not say it', function (): void {
    // «Activo» con el plan vencido es solo consulta: effectiveSubscriptionStatus().
    $vencida = empresaDePrueba(['subscription_status' => SubscriptionStatus::Active, 'subscription_expires_at' => now()->subDays(3)]);
    $vigente = empresaDePrueba(['subscription_status' => SubscriptionStatus::Active, 'subscription_expires_at' => now()->addYear()]);

    Livewire::test(ViewTenant::class, ['record' => $vencida->getRouteKey()])
        ->assertSee('Solo consulta')
        ->assertDontSee('Completo');

    Livewire::test(ViewTenant::class, ['record' => $vigente->getRouteKey()])
        ->assertSee('Completo')
        ->assertDontSee('Solo consulta');
});

it('lists the people of a company with their role on its page', function (): void {
    $tenant = empresaDePrueba();
    $ana = app(PlatformUserService::class)
        ->create('Ana Portería', 'ana@empresa.com', $tenant, 'porteria', $this->superAdmin)['user'];
    $ajeno = User::factory()->create();

    Livewire::test(UsersRelationManager::class, ['ownerRecord' => $tenant, 'pageClass' => ViewTenant::class])
        ->assertCanSeeTableRecords([$ana])
        ->assertCanNotSeeTableRecords([$ajeno])
        ->assertSee('Porteria');
});

it('titles the company page with its name', function (): void {
    $tenant = empresaDePrueba(['name' => 'Extractora del Llano']);

    $pagina = Livewire::test(ViewTenant::class, ['record' => $tenant->getRouteKey()]);

    expect($pagina->instance()->getTitle())->toBe('Extractora del Llano');
});

it('arrives at the new user form with the company already chosen', function (): void {
    $tenant = empresaDePrueba();

    Livewire::withQueryParams(['tenant' => $tenant->id])
        ->test(CreateUser::class)
        ->assertSchemaStateSet(['tenant_id' => $tenant->id]);
});

// ── Inicio ───────────────────────────────────────────────────────────────────

it('says on the banner what is failing, not just that something is', function (): void {
    $this->mock(SystemHealthService::class, function ($mock): void {
        $mock->shouldReceive('checks')->andReturn([
            ['key' => 'database', 'label' => 'Base de datos', 'status' => HealthStatus::Ok, 'value' => '1 ms', 'detail' => null],
            ['key' => 'backups', 'label' => 'Respaldos', 'status' => HealthStatus::Critical, 'value' => 'Ninguno', 'detail' => 'No hay respaldos.'],
        ]);
        $mock->shouldReceive('overallStatus')->andReturn(HealthStatus::Critical);
    });

    Livewire::test(PlatformDashboard::class)
        ->assertSee('Respaldos:')
        ->assertDontSee('Base de datos:');
});

it('runs the health checks once per load of the home page', function (): void {
    // overallStatus() los volvía a correr por dentro, y uno escribe y lee en disco.
    $this->mock(SystemHealthService::class, function ($mock): void {
        $mock->shouldReceive('checks')->once()->andReturn([]);
        $mock->shouldReceive('overallStatus')->andReturn(HealthStatus::Ok);
    });

    Livewire::test(PlatformDashboard::class)->assertSuccessful();
});

it('calls an unused company unused, not inactive', function (): void {
    // La tarjeta de arriba decía «Activas: 2» y debajo las dos salían «Inactiva».
    empresaDePrueba(['name' => 'Empresa Sin Uso', 'subscription_status' => SubscriptionStatus::Active]);

    Livewire::test(PlatformDashboard::class)
        ->assertSee('Sin uso reciente')
        ->assertDontSee('Inactiva');
});
