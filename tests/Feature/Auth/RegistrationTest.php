<?php

use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen redirects to the real product login', function () {
    // Sin registro público real: las cuentas las crea un administrador desde Filament.
    // La pantalla de registro del scaffold de Livewire/Flux no debe quedar huérfana.
    $response = $this->get(route('register'));

    $response->assertRedirect(route('filament.admin.auth.login'));
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
