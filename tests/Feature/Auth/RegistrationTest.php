<?php

use App\Models\User;

/*
 * El registro público está cerrado: las cuentas las crea un administrador, desde la
 * plataforma o desde el panel de su empresa.
 *
 * Hasta 2026-10 esta prueba comprobaba lo contrario —«new users can register»—, aunque
 * su propio comentario decía que no debía haber registro público. La pantalla redirigía
 * al login, pero el POST seguía creando cuentas sin empresa a quien lo enviara.
 */

test('nobody can create an account from outside', function () {
    $response = $this->post('/register', [
        'name' => 'Alguien de Fuera',
        'email' => 'intruso@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ]);

    // GET existe para los enlaces viejos; POST no.
    expect($response->status())->toBeIn([404, 405]);

    $this->assertGuest();
    expect(User::where('email', 'intruso@example.com')->exists())->toBeFalse();
});

test('the old registration address takes you to the login', function () {
    $this->get('/register')->assertRedirect(route('filament.admin.auth.login'));
});
