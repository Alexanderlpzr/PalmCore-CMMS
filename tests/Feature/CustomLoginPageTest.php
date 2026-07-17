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

it('la imagen del carrusel usa una ruta relativa a la raíz, no un host fijo', function (): void {
    // Con el disco local, el URL debe salir relativo a la raíz (/storage/...) para
    // cargar desde el mismo host de la visita —www o dominio pelado— y no un host
    // cruzado que la CSP bloquea. Sin esto, aparece el ícono de imagen rota.
    config(['filesystems.persistent_disk' => 'public']);

    $image = LoginBackgroundImage::factory()->create(['image_path' => 'login/planta.jpg']);

    expect($image->imageUrl())->toBe('/storage/login/planta.jpg')
        ->and($image->imageUrl())->not->toStartWith('http');
});
