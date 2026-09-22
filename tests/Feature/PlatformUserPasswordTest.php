<?php

use App\Domain\Platform\Services\UserPasswordService;
use App\Filament\Auth\EditProfile;
use App\Filament\Platform\Resources\Users\Pages\ListUsers;
use App\Filament\Platform\Resources\Users\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/*
 * Cambiar contraseñas desde el panel de plataforma, y la temporal que caduca al entrar.
 *
 * Lo que más importa aquí es lo que NO se puede: ver una contraseña (no existe en claro
 * en ninguna parte), quedarse con la temporal (el panel no se abre hasta cambiarla) y
 * seguir dentro con una sesión vieja después de un cambio.
 */

beforeEach(function (): void {
    $this->superAdmin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
    $this->tenant = Tenant::factory()->create(['name' => 'Extractora de Prueba']);

    $this->user = User::factory()->create([
        'name' => 'Persona de RRHH',
        'is_active' => true,
        'password' => 'Original-123',
    ]);
    $this->user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
});

function asPlatformSuperAdmin(User $superAdmin): void
{
    Filament::setCurrentPanel(Filament::getPanel('platform'));
    test()->actingAs($superAdmin);
}

// ── La pantalla ──────────────────────────────────────────────────────────────

it('lista a los usuarios de todas las empresas con su empresa', function (): void {
    asPlatformSuperAdmin($this->superAdmin);

    $otraEmpresa = Tenant::factory()->create();
    $otro = User::factory()->create();
    $otro->tenants()->attach($otraEmpresa->id, ['joined_at' => now()]);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->user, $otro])
        ->assertSee('Extractora de Prueba')
        ->filterTable('tenant', $otraEmpresa->id)
        ->assertCanSeeTableRecords([$otro])
        ->assertCanNotSeeTableRecords([$this->user]);
});

it('solo la ve el superadministrador', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('platform'));
    $this->actingAs($this->user);

    expect(UserResource::canViewAny())->toBeFalse();
});

it('las acciones no aparecen sobre la propia cuenta', function (): void {
    asPlatformSuperAdmin($this->superAdmin);

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('setPassword')->table($this->superAdmin))
        ->assertActionVisible(TestAction::make('setPassword')->table($this->user));
});

// ── Cambiar y generar ────────────────────────────────────────────────────────

it('cambia la contraseña y la deja temporal por omisión', function (): void {
    asPlatformSuperAdmin($this->superAdmin);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('setPassword')->table($this->user), data: [
            'must_change' => true,
            'password' => 'Temporal8',
            'password_confirmation' => 'Temporal8',
        ])
        ->assertHasNoActionErrors();

    $this->user->refresh();

    expect(Hash::check('Temporal8', $this->user->password))->toBeTrue()
        ->and($this->user->must_change_password)->toBeTrue()
        ->and($this->user->password_changed_at)->not->toBeNull();
});

it('exige que las dos contraseñas coincidan', function (): void {
    asPlatformSuperAdmin($this->superAdmin);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('setPassword')->table($this->user), data: [
            'must_change' => true,
            'password' => 'Temporal8',
            'password_confirmation' => 'Otra-cosa',
        ])
        ->assertHasActionErrors(['password']);

    expect(Hash::check('Original-123', $this->user->fresh()->password))->toBeTrue();
});

it('genera una temporal legible que no se guarda en claro', function (): void {
    $password = app(UserPasswordService::class)->generateTemporary($this->user);

    $this->user->refresh();

    expect($password)->toHaveLength(12)
        ->and($password)->not->toMatch('/[0O1lI]/')
        ->and($this->user->password)->not->toBe($password)
        ->and(Hash::check($password, $this->user->password))->toBeTrue()
        ->and($this->user->must_change_password)->toBeTrue();
});

it('la acción de temporal la muestra una vez en pantalla', function (): void {
    asPlatformSuperAdmin($this->superAdmin);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('temporaryPassword')->table($this->user))
        ->assertNotified();

    expect($this->user->fresh()->must_change_password)->toBeTrue();
});

it('cambiar la contraseña cierra sus sesiones y revoca sus tokens', function (): void {
    config(['session.driver' => 'database']);

    DB::table('sessions')->insert([
        'id' => 'sesion-vieja',
        'user_id' => $this->user->id,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);
    $this->user->createToken('app móvil');

    app(UserPasswordService::class)->setPassword($this->user, 'Nueva-Clave-9', mustChange: false);

    expect(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(0)
        ->and($this->user->tokens()->count())->toBe(0);
});

it('no deja desactivar al último superadministrador', function (): void {
    asPlatformSuperAdmin($this->superAdmin);

    $otroSuper = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
    $this->superAdmin->forceFill(['is_active' => false])->saveQuietly();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('toggleActive')->table($otroSuper))
        ->assertNotified('No puedes desactivar al último Super Admin activo de la plataforma. Debe existir al menos un Super Admin activo.');

    expect($otroSuper->fresh()->is_active)->toBeTrue();
});

it('desactiva y reactiva a un usuario', function (): void {
    asPlatformSuperAdmin($this->superAdmin);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('toggleActive')->table($this->user));

    expect($this->user->fresh()->is_active)->toBeFalse();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('toggleActive')->table($this->user->fresh()));

    expect($this->user->fresh()->is_active)->toBeTrue();
});

// ── La temporal caduca al entrar ─────────────────────────────────────────────

it('con contraseña temporal el panel lleva al Perfil y no abre nada más', function (): void {
    $this->user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($this->user->fresh())
        ->get("/admin/{$this->tenant->slug}")
        ->assertRedirect(Filament::getPanel('admin')->getProfileUrl());
});

it('sin contraseña temporal el panel no redirige al Perfil', function (): void {
    $response = $this->actingAs($this->user->fresh())->get("/admin/{$this->tenant->slug}");

    expect((string) $response->headers->get('Location'))->not->toContain('profile');
});

it('en el Perfil la temporal no se puede «cambiar» por ella misma', function (): void {
    app(UserPasswordService::class)->setPassword($this->user, 'Temporal8', mustChange: true);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->user->fresh());

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => 'Temporal8',
            'passwordConfirmation' => 'Temporal8',
            'currentPassword' => 'Temporal8',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);

    expect($this->user->fresh()->must_change_password)->toBeTrue();
});

it('en el Perfil la contraseña es obligatoria mientras sea temporal', function (): void {
    app(UserPasswordService::class)->setPassword($this->user, 'Temporal8', mustChange: true);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->user->fresh());

    Livewire::test(EditProfile::class)
        ->assertSee('Elija su contraseña')
        ->call('save')
        ->assertHasFormErrors(['password' => 'required']);
});

it('al elegir una propia deja de ser temporal', function (): void {
    app(UserPasswordService::class)->setPassword($this->user, 'Temporal8', mustChange: true);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->user->fresh());

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => 'Mi-Propia-Clave-2026',
            'passwordConfirmation' => 'Mi-Propia-Clave-2026',
            'currentPassword' => 'Temporal8',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->user->refresh();

    expect($this->user->must_change_password)->toBeFalse()
        ->and(Hash::check('Mi-Propia-Clave-2026', $this->user->password))->toBeTrue();
});

it('la app móvil no entrega token con una contraseña temporal', function (): void {
    app(UserPasswordService::class)->setPassword($this->user, 'Temporal8', mustChange: true);

    $this->postJson('/api/v1/tokens', [
        'email' => $this->user->email,
        'password' => 'Temporal8',
        'tenant_slug' => $this->tenant->slug,
        'token_name' => 'app',
    ])
        ->assertForbidden()
        ->assertJsonFragment(['message' => 'Tu contraseña es temporal. Entra primero a fronda.app desde un navegador para elegir una propia.']);
});
