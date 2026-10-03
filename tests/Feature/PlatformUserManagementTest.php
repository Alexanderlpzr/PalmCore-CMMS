<?php

use App\Domain\Platform\Enums\LoginLogEvent;
use App\Domain\Platform\Services\PlatformUserService;
use App\Filament\Platform\Resources\Users\Pages\CreateUser;
use App\Filament\Platform\Resources\Users\Pages\EditUser;
use App\Filament\Platform\Resources\Users\Pages\ListUsers;
use App\Filament\Platform\Resources\Users\Pages\ViewUser;
use App\Filament\Platform\Resources\Users\RelationManagers\AccountChangesRelationManager;
use App\Filament\Platform\Resources\Users\RelationManagers\ImpersonationsRelationManager;
use App\Filament\Platform\Resources\Users\RelationManagers\LoginLogsRelationManager;
use App\Filament\Platform\Resources\Users\RelationManagers\TenantsRelationManager;
use App\Filament\Platform\Resources\Users\Schemas\UserForm;
use App\Filament\Platform\Resources\Users\UserResource;
use App\Filament\Resources\Users\Pages\EditUser as TenantEditUser;
use App\Models\AuditLog;
use App\Models\ImpersonationLog;
use App\Models\LoginLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ImpersonationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * El panel de plataforma como lugar único para manejar las cuentas de todas las
 * empresas: alta, datos, empresas y roles, acceso, seguridad y actividad.
 *
 * Las reglas se prueban en PlatformUserServiceTest; aquí, que la pantalla las use y
 * que cada cosa esté donde se busca.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->superAdmin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);

    $this->pajuil = Tenant::factory()->create(['name' => 'Extractora El Pajuil']);
    $this->otra = Tenant::factory()->create(['name' => 'Otra Extractora']);
    app(TenantRolesSeeder::class)->run($this->pajuil);
    app(TenantRolesSeeder::class)->run($this->otra);

    $this->ana = app(PlatformUserService::class)
        ->create('Ana Portería', 'ana@elpajuil.com', $this->pajuil, 'porteria', $this->superAdmin)['user'];

    enLaPlataformaComo($this->superAdmin);
});

function enLaPlataformaComo(User $user): void
{
    Filament::setCurrentPanel(Filament::getPanel('platform'));
    test()->actingAs($user);
}

/** Las cuatro pestañas de la ficha se prueban montadas sobre la página de ver. */
function pestanaDe(string $relationManager, User $user): mixed
{
    return Livewire::test($relationManager, [
        'ownerRecord' => $user,
        'pageClass' => ViewUser::class,
    ]);
}

// ── Alta ─────────────────────────────────────────────────────────────────────

it('creates an account in any company without entering its panel', function (): void {
    // Antes, las cuentas de RRHH y portería solo se podían crear por consola.
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Rosa Nómina',
            'email' => 'rosa@elpajuil.com',
            'tenant_id' => $this->pajuil->id,
            'role' => 'talento-humano',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $rosa = User::where('email', 'rosa@elpajuil.com')->sole();

    expect(app(PlatformUserService::class)->rolesIn($rosa, $this->pajuil))->toBe(['talento-humano'])
        ->and($rosa->must_change_password)->toBeTrue();
});

it('offers only the roles of the chosen company', function (): void {
    expect(UserForm::rolesOf($this->pajuil->id))
        ->toBe([
            'administrador-general' => 'Administrador General',
            'porteria' => 'Porteria',
            'talento-humano' => 'Talento Humano',
        ])
        ->and(UserForm::rolesOf(null))->toBe([]);
});

it('says why it cannot create an account with an email already taken', function (): void {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Otra Ana',
            'email' => 'ana@elpajuil.com',
            'tenant_id' => $this->pajuil->id,
            'role' => 'porteria',
        ])
        ->call('create')
        ->assertNotified('Ya existe un usuario con el correo ana@elpajuil.com.');

    expect(User::where('email', 'ana@elpajuil.com')->count())->toBe(1);
});

// ── Datos ────────────────────────────────────────────────────────────────────

it('edits the name and the email of any person', function (): void {
    Livewire::test(EditUser::class, ['record' => $this->ana->getRouteKey()])
        ->fillForm([
            'name' => 'Ana María Pérez',
            'email' => 'anamaria@elpajuil.com',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->ana->refresh()->name)->toBe('Ana María Pérez')
        ->and($this->ana->email)->toBe('anamaria@elpajuil.com');
});

it('opens the page of a person with their four tabs', function (): void {
    Livewire::test(ViewUser::class, ['record' => $this->ana->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Ana Portería')
        ->assertSee('ana@elpajuil.com');

    expect(UserResource::getRelations())->toBe([
        TenantsRelationManager::class,
        LoginLogsRelationManager::class,
        ImpersonationsRelationManager::class,
        AccountChangesRelationManager::class,
    ]);
});

it('never offers to delete an account, not even to the super administrator', function (): void {
    // Se desactiva: las OT, los paros y la auditoría apuntan a la persona.
    expect(UserResource::canDelete($this->ana))->toBeFalse()
        ->and(UserResource::canDeleteAny())->toBeFalse();
});

it('keeps every page closed to anyone who is not a super administrator', function (): void {
    // Un administrador de empresa: el caso que importa, porque la política de usuarios
    // le deja ver y editar cuentas, y aquí serían las de todas las empresas.
    $adminDeEmpresa = app(PlatformUserService::class)
        ->create('Admin Pajuil', 'admin@elpajuil.com', $this->pajuil, 'administrador-general', $this->superAdmin)['user'];

    $this->actingAs($adminDeEmpresa);

    // No ve ninguna: lo manda a su propio panel (RedirectNonSuperAdminsToAdminPanel).
    $this->get(UserResource::getUrl('index'))->assertRedirect(url('admin'));
    $this->get(UserResource::getUrl('create'))->assertRedirect(url('admin'));
    $this->get(UserResource::getUrl('view', ['record' => $this->ana]))->assertRedirect(url('admin'));
    $this->get(UserResource::getUrl('edit', ['record' => $this->ana]))->assertRedirect(url('admin'));

    // Y el recurso no depende solo del middleware del panel.
    expect(UserResource::canView($this->ana))->toBeFalse()
        ->and(UserResource::canEdit($this->ana))->toBeFalse()
        ->and(UserResource::canCreate())->toBeFalse();
});

it('saves a person from the panel of their company too', function (): void {
    // El campo de foto es el mismo en los dos paneles. En el de empresa, guardar la
    // ficha de un usuario reventaba fuera de producción por la misma causa —el campo
    // lee `avatar_path` del registro al validar— y ninguna prueba la había guardado.
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->pajuil);
    setPermissionsTeamId($this->pajuil->id);

    Livewire::test(TenantEditUser::class, ['record' => $this->ana->getRouteKey()])
        ->fillForm(['name' => 'Ana desde la empresa'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->ana->refresh()->name)->toBe('Ana desde la empresa');
});

// ── Acceso y seguridad ───────────────────────────────────────────────────────

it('signs a person out of every device', function (): void {
    $this->ana->createToken('app-movil');

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('signOut')->table($this->ana))
        ->assertNotified('Sesiones cerradas');

    expect($this->ana->tokens()->count())->toBe(0);
});

it('offers to remove two-step verification only to someone who has it', function (): void {
    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('resetTwoFactor')->table($this->ana));

    $this->ana->forceFill([
        'two_factor_secret' => encrypt('secreto'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    Livewire::test(ListUsers::class)
        ->assertActionVisible(TestAction::make('resetTwoFactor')->table($this->ana))
        ->callAction(TestAction::make('resetTwoFactor')->table($this->ana));

    expect($this->ana->refresh()->two_factor_confirmed_at)->toBeNull();
});

it('names another super administrator', function (): void {
    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('toggleSuperAdmin')->table($this->ana))
        ->assertNotified('Ahora es superadministrador');

    expect($this->ana->refresh()->is_super_admin)->toBeTrue();
});

it('enters as another person, with the reason on record', function (): void {
    // La suplantación en sí —sesión, registro, salida— la prueba ImpersonationTest por
    // la ruta HTTP. Livewire prueba sus acciones sin middleware, y sin el que abre la
    // sesión el servicio no puede guardar nada en ella. Aquí se prueba lo que es de la
    // acción: a quién, con qué motivo, y a dónde lleva después.
    $this->mock(ImpersonationService::class, function ($mock): void {
        $mock->shouldReceive('isImpersonating')->andReturnFalse();
        $mock->shouldReceive('start')
            ->once()
            ->withArgs(fn (User $actor, User $target, ?string $reason): bool => $actor->is($this->superAdmin)
                && $target->is($this->ana)
                && $reason === 'No le aparece el botón para cerrar OT')
            ->andReturn(new ImpersonationLog);
    });

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('impersonate')->table($this->ana), data: [
            'reason' => 'No le aparece el botón para cerrar OT',
        ])
        ->assertRedirect('/admin');
});

it('never offers to enter as another super administrator', function (): void {
    $otroSuper = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('impersonate')->table($otroSuper));
});

it('offers the same actions on the page of the person', function (): void {
    $this->ana->createToken('app-movil');

    Livewire::test(ViewUser::class, ['record' => $this->ana->getRouteKey()])
        ->callAction('signOut')
        ->assertNotified('Sesiones cerradas');

    expect($this->ana->tokens()->count())->toBe(0);
});

it('hides from the own row what is managed from the Profile', function (): void {
    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('toggleActive')->table($this->superAdmin))
        ->assertActionHidden(TestAction::make('signOut')->table($this->superAdmin))
        ->assertActionHidden(TestAction::make('toggleSuperAdmin')->table($this->superAdmin));
});

// ── Empresas y roles ─────────────────────────────────────────────────────────

it('shows each company of the person with its role', function (): void {
    pestanaDe(TenantsRelationManager::class, $this->ana)
        ->assertCanSeeTableRecords([$this->pajuil])
        ->assertSee('Porteria');
});

it('changes the role of the person in one company', function (): void {
    pestanaDe(TenantsRelationManager::class, $this->ana)
        ->callAction(TestAction::make('changeRole')->table($this->pajuil), data: ['role' => 'talento-humano'])
        ->assertNotified('Rol cambiado');

    expect(app(PlatformUserService::class)->rolesIn($this->ana, $this->pajuil))->toBe(['talento-humano']);
});

it('gives the person access to a second company', function (): void {
    pestanaDe(TenantsRelationManager::class, $this->ana)
        ->callAction(TestAction::make('addToTenant')->table(), data: [
            'tenant_id' => $this->otra->id,
            'role' => 'administrador-general',
        ])
        ->assertNotified('Acceso concedido');

    expect(app(PlatformUserService::class)->rolesIn($this->ana, $this->otra))->toBe(['administrador-general'])
        ->and(app(PlatformUserService::class)->rolesIn($this->ana, $this->pajuil))->toBe(['porteria']);
});

it('removes the access of the person to a company', function (): void {
    pestanaDe(TenantsRelationManager::class, $this->ana)
        ->callAction(TestAction::make('removeFromTenant')->table($this->pajuil))
        ->assertNotified('Acceso retirado');

    expect($this->ana->tenants()->exists())->toBeFalse();
});

// ── Actividad ────────────────────────────────────────────────────────────────

it('shows the logins of the person and nobody else', function (): void {
    $suyo = LoginLog::create([
        'user_id' => $this->ana->id, 'email' => $this->ana->email,
        'event' => LoginLogEvent::Login->value, 'occurred_at' => now(),
    ]);
    $ajeno = LoginLog::create([
        'user_id' => $this->superAdmin->id, 'email' => $this->superAdmin->email,
        'event' => LoginLogEvent::Login->value, 'occurred_at' => now(),
    ]);

    pestanaDe(LoginLogsRelationManager::class, $this->ana)
        ->assertCanSeeTableRecords([$suyo])
        ->assertCanNotSeeTableRecords([$ajeno]);
});

it('shows who entered as the person and why', function (): void {
    $vez = ImpersonationLog::create([
        'impersonator_id' => $this->superAdmin->id,
        'impersonated_user_id' => $this->ana->id,
        'tenant_id' => $this->pajuil->id,
        'started_at' => now()->subMinutes(10),
        'ended_at' => now(),
        'duration_seconds' => 600,
        'reason' => 'Revisar su calendario',
    ]);

    pestanaDe(ImpersonationsRelationManager::class, $this->ana)
        ->assertCanSeeTableRecords([$vez])
        ->assertSee('Revisar su calendario')
        ->assertSee('10 min');
});

it('shows what was changed in the account, by whom', function (): void {
    app(PlatformUserService::class)->changeRole($this->ana, $this->pajuil, 'talento-humano', $this->superAdmin);

    // La auditoría se escribe al terminar la petición; en una prueba hay que provocarlo.
    app()->terminate();

    $cambios = AuditLog::where('auditable_type', User::class)->where('auditable_id', $this->ana->id)->get();

    expect($cambios->pluck('event')->sort()->values()->all())->toBe(['created', 'role_changed']);

    pestanaDe(AccountChangesRelationManager::class, $this->ana)
        ->assertCanSeeTableRecords($cambios)
        ->assertSee('Rol cambiado')
        ->assertSee('Extractora El Pajuil: Porteria → Talento Humano');
});
