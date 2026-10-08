<?php

/*
 * Las cabeceras de seguridad. La de permisos deja la cámara al propio sitio: la Portería
 * lee los carnés con ella, y con `camera=()` Chrome en Android la negaba sin preguntar.
 */

it('lets the site itself use the camera and nobody else', function (): void {
    $response = $this->get('/admin/login');

    expect($response->headers->get('Permissions-Policy'))
        ->toContain('camera=(self)')
        ->toContain('microphone=()')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY');
});
