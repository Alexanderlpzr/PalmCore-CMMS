<?php

it('redirige www al dominio pelado con un 301', function (): void {
    $response = $this->get('https://www.fronda.app/admin/login');

    $response->assertStatus(301)
        ->assertRedirect('https://fronda.app/admin/login');
});

it('conserva la ruta y la query al redirigir', function (): void {
    $response = $this->get('https://www.fronda.app/equipment/qr/abc?x=1');

    $response->assertRedirect('https://fronda.app/equipment/qr/abc?x=1');
});

it('no toca las visitas al dominio pelado', function (): void {
    // El dominio canónico no debe redirigir a sí mismo (evita bucles).
    $this->get('https://fronda.app/admin/login')->assertOk();
});

it('no redirige un POST por www (perdería el cuerpo)', function (): void {
    // Un 301 sobre POST perdería los datos; solo GET/HEAD se canonicalizan.
    $response = $this->post('https://www.fronda.app/login', []);

    expect($response->getStatusCode())->not->toBe(301);
});
