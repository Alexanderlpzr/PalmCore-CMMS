<?php

use Symfony\Component\Finder\Finder;

/*
 * Un comentario de Blade mal escrito se imprime en pantalla.
 *
 * `{{-- … --}}` es un comentario y desaparece al renderizar; `{-- … --}`, con una sola
 * llave, no significa nada para Blade y sale tal cual en medio de la página. Pasó de
 * verdad: la tabla anual de Energía mostró durante un despliegue el texto
 * «{-- Los tres renglones amarillos de la hoja… --}» encima de las columnas, y no lo vio
 * ningún test porque ninguno renderiza esa vista con datos.
 *
 * Es barato comprobarlo leyendo los archivos, y es la clase de error que solo se nota si
 * alguien mira la pantalla.
 */
it('ninguna vista imprime un comentario de Blade a medio escribir', function (): void {
    $sospechosas = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $archivo) {
        $contenido = $archivo->getContents();

        // `{--` que no venga precedido de otra llave: eso es un comentario mal cerrado.
        if (preg_match('/(?<!\{)\{--\s/', $contenido) === 1) {
            $sospechosas[] = $archivo->getRelativePathname();
        }
    }

    expect($sospechosas)->toBe([]);
});
