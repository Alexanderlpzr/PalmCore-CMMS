<?php

use App\Domain\Platform\Services\PlatformUserService;
use App\Exceptions\BusinessRuleException;
use App\Infrastructure\Audit\Jobs\WriteAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;

/*
 * Lo que el superadministrador hace sobre las cuentas de cualquier empresa.
 *
 * Las pruebas que más importan son las de lo que NO se puede: quitarle el acceso al
 * último superadministrador, quitárselo a uno mismo, repetir un correo, o asignar un
 * rol que en esa empresa no existe.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->service = app(PlatformUserService::class);
    $this->actor = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);

    $this->pajuil = Tenant::factory()->create(['name' => 'Extractora El Pajuil']);
    $this->otra = Tenant::factory()->create(['name' => 'Otra Extractora']);
    app(TenantRolesSeeder::class)->run($this->pajuil);
    app(TenantRolesSeeder::class)->run($this->otra);
});

/** Una persona de la empresa con un rol, como la dejaría el alta. */
function personaDe(Tenant $tenant, string $role = 'porteria', array $attributes = []): User
{
    return test()->service->create(
        $attributes['name'] ?? fake()->name(),
        $attributes['email'] ?? fake()->unique()->safeEmail(),
        $tenant,
        $role,
        test()->actor,
    )['user'];
}

/** Los eventos de auditoría que se despacharon sobre una cuenta. */
function eventosDeAuditoria(User $user): array
{
    app()->terminate();

    return Queue::pushed(WriteAuditLog::class, fn (WriteAuditLog $job): bool => $job->modelClass === User::class
        && $job->modelKey === (string) $user->getKey())
        ->map(fn (WriteAuditLog $job): string => $job->event)
        ->values()
        ->all();
}

// ── El alta ──────────────────────────────────────────────────────────────────

it('creates a person in a company with their role and a temporary password', function (): void {
    $result = $this->service->create('Ana Portería', 'ana@elpajuil.com', $this->pajuil, 'porteria', $this->actor);

    $ana = $result['user'];

    expect($ana->tenants->pluck('id')->all())->toBe([$this->pajuil->id])
        ->and($this->service->rolesIn($ana, $this->pajuil))->toBe(['porteria'])
        ->and($ana->must_change_password)->toBeTrue()
        ->and($ana->is_super_admin)->toBeFalse()
        ->and(Hash::check($result['password'], $ana->password))->toBeTrue();
});

it('refuses an email that already exists, whatever its capitals', function (): void {
    personaDe($this->pajuil, attributes: ['email' => 'ana@elpajuil.com']);

    expect(fn () => $this->service->create('Otra Ana', 'ANA@elpajuil.com', $this->pajuil, 'porteria', $this->actor))
        ->toThrow(BusinessRuleException::class, 'Ya existe un usuario con el correo');
});

it('refuses an email that belongs to a deleted account', function (): void {
    // La base de datos tampoco lo dejaría: el índice único cubre las eliminadas.
    personaDe($this->pajuil, attributes: ['email' => 'borrada@elpajuil.com'])->delete();

    expect(fn () => $this->service->create('Nueva', 'borrada@elpajuil.com', $this->pajuil, 'porteria', $this->actor))
        ->toThrow(BusinessRuleException::class);
});

it('refuses a role the company does not have', function (): void {
    expect(fn () => $this->service->create('Ana', 'ana@elpajuil.com', $this->pajuil, 'gerente-galactico', $this->actor))
        ->toThrow(BusinessRuleException::class, 'no existe en Extractora El Pajuil');
});

// ── Los datos de la cuenta ───────────────────────────────────────────────────

it('changes the name and the email', function (): void {
    $ana = personaDe($this->pajuil, attributes: ['name' => 'Ana', 'email' => 'ana@elpajuil.com']);

    $this->service->updateAccount($ana, 'Ana María Pérez', 'anamaria@elpajuil.com', false, $this->actor);

    expect($ana->refresh()->name)->toBe('Ana María Pérez')
        ->and($ana->email)->toBe('anamaria@elpajuil.com');
});

it('lets a person keep their own email when editing', function (): void {
    $ana = personaDe($this->pajuil, attributes: ['email' => 'ana@elpajuil.com']);

    $this->service->updateAccount($ana, 'Ana Renombrada', 'ana@elpajuil.com', false, $this->actor);

    expect($ana->refresh()->name)->toBe('Ana Renombrada');
});

it('refuses an empty name or a malformed email', function (): void {
    $ana = personaDe($this->pajuil);

    expect(fn () => $this->service->updateAccount($ana, '   ', 'ana@elpajuil.com', false, $this->actor))
        ->toThrow(BusinessRuleException::class, 'vacío')
        ->and(fn () => $this->service->updateAccount($ana, 'Ana', 'no-es-un-correo', false, $this->actor))
        ->toThrow(BusinessRuleException::class, 'no es un correo válido');
});

// ── Empresas y roles ─────────────────────────────────────────────────────────

it('changes the role inside a company', function (): void {
    $ana = personaDe($this->pajuil, 'porteria');

    $this->service->changeRole($ana, $this->pajuil, 'talento-humano', $this->actor);

    expect($this->service->rolesIn($ana, $this->pajuil))->toBe(['talento-humano']);
});

it('gives access to a second company without touching the first', function (): void {
    // Los roles de Spatie se separan por empresa: dar uno en la segunda no puede
    // cambiar el que tiene en la primera.
    $ana = personaDe($this->pajuil, 'porteria');

    $this->service->addToTenant($ana, $this->otra, 'administrador-general', $this->actor);

    expect($this->service->rolesIn($ana, $this->pajuil))->toBe(['porteria'])
        ->and($this->service->rolesIn($ana, $this->otra))->toBe(['administrador-general'])
        ->and($ana->tenants()->wherePivot('is_primary_tenant', true)->pluck('tenants.id')->all())
        ->toBe([$this->pajuil->id]);
});

it('refuses to add a person twice to the same company', function (): void {
    $ana = personaDe($this->pajuil);

    expect(fn () => $this->service->addToTenant($ana, $this->pajuil, 'porteria', $this->actor))
        ->toThrow(BusinessRuleException::class, 'ya pertenece');
});

it('removes access to a company and hands the primary role to the next one', function (): void {
    $ana = personaDe($this->pajuil, 'porteria');
    $this->service->addToTenant($ana, $this->otra, 'porteria', $this->actor);

    $this->service->removeFromTenant($ana, $this->pajuil, $this->actor);

    expect($ana->tenants()->pluck('tenants.id')->all())->toBe([$this->otra->id])
        ->and($ana->tenants()->first()->pivot->is_primary_tenant)->toBeTrue()
        ->and($this->service->rolesIn($ana, $this->pajuil))->toBe([]);
});

it('leaves the permission context as it found it', function (): void {
    // La empresa activa de Spatie es una variable global de la petición: si una
    // operación la dejara apuntando a otra empresa, el resto vería permisos ajenos.
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->otra->id);

    personaDe($this->pajuil);

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($this->otra->id);
});

// ── Acceso ───────────────────────────────────────────────────────────────────

it('deactivates an account and closes its open sessions', function (): void {
    config()->set('session.driver', 'database');
    $ana = personaDe($this->pajuil);
    DB::table('sessions')->insert([
        'id' => 'sesion-de-ana', 'user_id' => $ana->id, 'payload' => '', 'last_activity' => time(),
    ]);

    $this->service->setActive($ana, false, $this->actor);

    expect($ana->refresh()->is_active)->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $ana->id)->exists())->toBeFalse();
});

it('promotes a second super administrator', function (): void {
    // Con uno solo, si esa cuenta se bloquea la plataforma solo se maneja por consola.
    $ana = personaDe($this->pajuil);

    $this->service->grantSuperAdmin($ana, $this->actor);

    expect($ana->refresh()->is_super_admin)->toBeTrue();
});

it('refuses to promote an inactive account', function (): void {
    $ana = personaDe($this->pajuil);
    $this->service->setActive($ana, false, $this->actor);

    expect(fn () => $this->service->grantSuperAdmin($ana->refresh(), $this->actor))
        ->toThrow(BusinessRuleException::class, 'Active la cuenta');
});

it('demotes a super administrator when another remains', function (): void {
    $otro = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);

    $this->service->revokeSuperAdmin($otro, $this->actor);

    expect($otro->refresh()->is_super_admin)->toBeFalse();
});

it('never leaves the platform without an active super administrator', function (): void {
    // En el uso normal no puede pasar: quien actúa es un superadministrador activo y
    // no puede tocarse a sí mismo, así que siempre queda él. La regla es un respaldo
    // que no depende de eso — por ejemplo, si algún día esto se llama desde consola.
    $ultimo = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
    $this->actor->forceFill(['is_active' => false])->saveQuietly();
    $sinAcceso = User::factory()->create(['is_super_admin' => true, 'is_active' => false]);

    expect(fn () => $this->service->revokeSuperAdmin($ultimo, $sinAcceso))
        ->toThrow(BusinessRuleException::class, 'último');

    expect($ultimo->refresh()->is_super_admin)->toBeTrue();
});

it('refuses to act on the own account from here', function (): void {
    expect(fn () => $this->service->setActive($this->actor, false, $this->actor))
        ->toThrow(BusinessRuleException::class, 'Perfil')
        ->and(fn () => $this->service->revokeSuperAdmin($this->actor, $this->actor))
        ->toThrow(BusinessRuleException::class, 'Perfil')
        ->and(fn () => $this->service->signOutEverywhere($this->actor, $this->actor))
        ->toThrow(BusinessRuleException::class, 'Perfil');
});

// ── Seguridad ────────────────────────────────────────────────────────────────

it('unlocks someone who lost the phone with their two-step verification', function (): void {
    $ana = personaDe($this->pajuil);
    $ana->forceFill([
        'two_factor_secret' => encrypt('secreto'),
        'two_factor_recovery_codes' => encrypt('[]'),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $ana->passkeys()->create([
        'name' => 'Teléfono perdido',
        'credential_id' => 'cred-1',
        'credential' => ['public_key' => 'x'],
    ]);

    $this->service->resetTwoFactor($ana, $this->actor);

    expect($ana->refresh()->two_factor_confirmed_at)->toBeNull()
        ->and($ana->two_factor_secret)->toBeNull()
        ->and($ana->passkeys()->count())->toBe(0);
});

it('signs someone out everywhere without changing the password', function (): void {
    $ana = personaDe($this->pajuil);
    $ana->createToken('app-movil');
    $hash = $ana->password;

    $this->service->signOutEverywhere($ana, $this->actor);

    expect($ana->tokens()->count())->toBe(0)
        ->and($ana->refresh()->password)->toBe($hash);
});

// ── La auditoría ─────────────────────────────────────────────────────────────

it('records every change made to an account from the platform', function (): void {
    Queue::fake();

    $ana = personaDe($this->pajuil, 'porteria');
    $this->service->changeRole($ana, $this->pajuil, 'talento-humano', $this->actor);
    $this->service->temporaryPassword($ana, $this->actor);
    $this->service->setActive($ana, false, $this->actor);

    expect(eventosDeAuditoria($ana))
        ->toBe(['created', 'role_changed', 'password_temporary', 'deactivated']);
});

it('never puts a password or a secret in the audit trail', function (): void {
    // El trait Auditable guardaría la fila entera: el hash, el secreto de dos pasos.
    // Aquí cada entrada lleva solo lo que se eligió poner.
    Queue::fake();

    $ana = personaDe($this->pajuil);
    $this->service->setPassword($ana, 'Una-Clave-Nueva-123', false, $this->actor);
    $this->service->resetTwoFactor($ana, $this->actor);

    app()->terminate();

    $volcado = Queue::pushed(WriteAuditLog::class)
        ->map(fn (WriteAuditLog $job): string => json_encode([$job->oldValues, $job->newValues]))
        ->implode(' ');

    expect($volcado)
        ->not->toContain('Una-Clave-Nueva-123')
        ->not->toContain($ana->refresh()->password)
        ->not->toContain('two_factor_secret');
});

it('does not record an edit that changed nothing', function (): void {
    Queue::fake();

    $ana = personaDe($this->pajuil, attributes: ['name' => 'Ana', 'email' => 'ana@elpajuil.com']);
    $this->service->updateAccount($ana, 'Ana', 'ana@elpajuil.com', false, $this->actor);

    expect(eventosDeAuditoria($ana))->toBe(['created']);
});
