<?php

use App\Filament\Pages\Auth\Login;
use App\Models\LoginBackgroundImage;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

// ── La página carga en ambos paneles ─────────────────────────────────────────

it('la pantalla de login del panel admin se renderiza', function (): void {
    $this->get('/admin/login')->assertOk();
});

it('la pantalla de login del panel de plataforma se renderiza', function (): void {
    $this->get('/platform/login')->assertOk();
});

// ── La autenticación real sigue funcionando (regresión) ──────────────────────

it('un usuario puede autenticarse con credenciales validas', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $user->email,
            'password' => 'password',
        ])
        ->call('authenticate');

    $this->assertAuthenticatedAs($user);
});

it('rechaza credenciales invalidas', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $user->email,
            'password' => 'contraseña-incorrecta',
        ])
        ->call('authenticate')
        ->assertHasFormErrors();

    $this->assertGuest();
});

// ── El carrusel ───────────────────────────────────────────────────────────────

it('getBackgroundImages solo trae imagenes activas', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $active = LoginBackgroundImage::factory()->create(['caption' => 'Activa']);
    LoginBackgroundImage::factory()->inactive()->create(['caption' => 'Inactiva']);

    $login = new Login;

    expect($login->getBackgroundImages()->pluck('caption')->all())->toBe(['Activa']);
});

it('se renderiza sin errores cuando no hay imagenes activas', function (): void {
    $this->get('/admin/login')->assertOk();
});
