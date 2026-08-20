<?php

/**
 * El logo y los favicon conservan su nombre entre versiones. Sin una marca de
 * versión en la URL, cambiar la identidad gráfica solo lo ve quien fuerce una
 * recarga: el navegador sigue pintando el archivo que ya tenía en caché.
 */
it('cuelga la fecha del archivo de la URL del asset', function (): void {
    $url = branding_asset('images/logo.png');

    expect($url)
        ->toContain('images/logo.png')
        ->toContain('?v='.filemtime(public_path('images/logo.png')));
});

it('devuelve la URL a secas cuando el archivo no existe', function (): void {
    $url = branding_asset('images/no-existe-este-archivo.png');

    expect($url)
        ->toContain('images/no-existe-este-archivo.png')
        ->not->toContain('?v=');
});

it('cambia la versión cuando el archivo cambia', function (): void {
    $path = public_path('images/logo.png');
    $original = filemtime($path);
    $antes = branding_asset('images/logo.png');

    try {
        touch($path, $original + 60);
        clearstatcache(true, $path);

        expect(branding_asset('images/logo.png'))->not->toBe($antes);
    } finally {
        // El asset es un archivo real del repositorio: se deja como estaba.
        touch($path, $original);
        clearstatcache(true, $path);
    }
});
