<?php

use App\Domain\Platform\Enums\LoginLogEvent;
use App\Filament\Platform\Resources\LoginLogs\LoginLogResource;
use App\Filament\Platform\Resources\LoginLogs\Pages\ListLoginLogs;
use App\Models\LoginLog;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

// ── Captura de eventos ───────────────────────────────────────────────────────

it('un login exitoso queda registrado con el usuario y la IP', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $log = LoginLog::where('user_id', $user->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe(LoginLogEvent::Login)
        ->and($log->email)->toBe($user->email)
        ->and($log->ip_address)->not->toBeNull();
});

it('un login exitoso actualiza last_login_at y last_login_ip del usuario', function (): void {
    $user = User::factory()->create(['last_login_at' => null]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $user->refresh();

    expect($user->last_login_at)->not->toBeNull()
        ->and($user->last_login_ip)->not->toBeNull();
});

it('un intento fallido con credenciales de un usuario real queda enlazado a ese usuario', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'contraseña-incorrecta',
    ]);

    $log = LoginLog::where('event', LoginLogEvent::Failed->value)->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($user->id)
        ->and($log->email)->toBe($user->email);
});

it('un intento fallido con un correo que no existe queda registrado sin usuario', function (): void {
    $this->post(route('login.store'), [
        'email' => 'nadie@fronda.app',
        'password' => 'lo-que-sea',
    ]);

    $log = LoginLog::where('event', LoginLogEvent::Failed->value)->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBeNull()
        ->and($log->email)->toBe('nadie@fronda.app');
});

it('cerrar sesión queda registrado', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'));

    $log = LoginLog::where('user_id', $user->id)->where('event', LoginLogEvent::Logout->value)->first();

    expect($log)->not->toBeNull();
});

// ── El recurso de Plataforma ─────────────────────────────────────────────────

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('platform'));
});

it('el superadministrador puede ver los registros de acceso', function (): void {
    $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]));

    Livewire::test(ListLoginLogs::class)->assertOk();
});

it('un usuario normal no puede ver el recurso', function (): void {
    $this->actingAs(User::factory()->create(['is_super_admin' => false, 'is_active' => true]));

    expect(LoginLogResource::canViewAny())->toBeFalse();
});

it('el filtro de empresa solo muestra accesos de usuarios de esa empresa', function (): void {
    $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_active' => true]));

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->create();
    $userA->tenants()->attach($tenantA->id, ['joined_at' => now()]);

    $userB = User::factory()->create();
    $userB->tenants()->attach($tenantB->id, ['joined_at' => now()]);

    LoginLog::create([
        'user_id' => $userA->id, 'email' => $userA->email,
        'event' => LoginLogEvent::Login->value, 'occurred_at' => now(),
    ]);
    LoginLog::create([
        'user_id' => $userB->id, 'email' => $userB->email,
        'event' => LoginLogEvent::Login->value, 'occurred_at' => now(),
    ]);

    Livewire::test(ListLoginLogs::class)
        ->filterTable('tenant', $tenantA->id)
        ->assertCanSeeTableRecords(LoginLog::where('user_id', $userA->id)->get())
        ->assertCanNotSeeTableRecords(LoginLog::where('user_id', $userB->id)->get());
});
