<?php

/*
 * Reglas de presentación de las 69 tablas del producto.
 *
 * Son defectos que no rompen ningún test funcional — la tabla se renderiza
 * perfectamente con los precios desalineados y el texto cortado sin forma de
 * leerlo — así que sin este archivo vuelven a colarse en la siguiente columna
 * que alguien añada. Se comprueba leyendo el código, no renderizando: lo que se
 * quiere fijar es la convención, y una tabla nueva la hereda sin escribir nada.
 */

/** Recorta el archivo a la parte que declara columnas, sin filtros ni acciones. */
function columnChunks(string $source): array
{
    $end = '/->(filters|recordActions|toolbarActions|headerActions|actions|bulkActions|groups|'
        .'defaultSort|striped|emptyState\w*|paginat\w*|recordUrl|recordAction|reorderable|'
        .'modifyQueryUsing|persist\w*)\(|\n\s*\]\)/';

    $chunks = [];

    foreach (preg_split('/(?=\b\w*Column::make\()/', $source) as $part) {
        if (! preg_match('/^\w*Column::make\(/', $part)) {
            continue;
        }

        // Sin este corte, la última columna del archivo absorbe los filtros y las
        // acciones, y cualquier ->money() que aparezca allí da un falso positivo.
        $chunks[] = preg_match($end, $part, $m, PREG_OFFSET_CAPTURE)
            ? substr($part, 0, $m[0][1])
            : $part;
    }

    return $chunks;
}

/** @return array<int, string> rutas de todos los archivos que declaran una tabla */
function tableFiles(): array
{
    $files = [];
    $base = dirname(__DIR__, 2).'/app/Filament';

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        if (! str_contains($source, 'columns([')) {
            continue;
        }

        $files[] = $file->getPathname();
    }

    sort($files);

    return $files;
}

function relativePath(string $path): string
{
    return str_replace('\\', '/', substr($path, strlen(dirname(__DIR__, 2)) + 1));
}

it('encuentra las tablas del proyecto', function (): void {
    // Si esto baja de golpe, el recorrido se rompió y los demás tests de este
    // archivo estarían pasando sobre una lista vacía.
    //
    // Bajó de 69 a 66 al retirar código inalcanzable: CriticalAlertsWidget, que
    // no estaba en ningún getWidgets(), y los relation managers de Técnicos y
    // Contratistas, que no estaban en ningún getRelations() desde que la OT se
    // colapsó a «crear y cerrar».
    expect(tableFiles())->toHaveCount(66);
});

it('alinea a la derecha toda columna de dinero o cantidad', function (): void {
    $offenders = [];

    foreach (tableFiles() as $file) {
        foreach (columnChunks(file_get_contents($file)) as $chunk) {
            $isNumeric = str_contains($chunk, '->money(')
                || str_contains($chunk, '->numeric(')
                || preg_match('/number_format\(|format_hours_minutes\(/', $chunk);

            // Las píldoras se quedan como estén: la alineación de una píldora no
            // ayuda a comparar cifras, que es el problema que esto resuelve.
            if (! $isNumeric || str_contains($chunk, '->badge()')) {
                continue;
            }

            if (preg_match('/align(End|Right)\(\)|alignment\(/', $chunk)) {
                continue;
            }

            preg_match("/^\w*Column::make\(\s*'([^']*)'/", $chunk, $name);
            $offenders[] = relativePath($file).' → '.($name[1] ?? '?');
        }
    }

    // Con los dígitos desalineados no se pueden comparar dos importes de un
    // vistazo, que es justo para lo que existe una columna de costos.
    expect($offenders)->toBe([]);
});

it('deja leer el texto que recorta', function (): void {
    $offenders = [];

    foreach (tableFiles() as $file) {
        foreach (columnChunks(file_get_contents($file)) as $chunk) {
            if (! str_contains($chunk, '->limit(')) {
                continue;
            }

            if (str_contains($chunk, '->tooltip(')) {
                continue;
            }

            preg_match("/^\w*Column::make\(\s*'([^']*)'/", $chunk, $name);
            $offenders[] = relativePath($file).' → '.($name[1] ?? '?');
        }
    }

    // ->limit() a secas corta el valor y no deja forma de ver el resto, ni con el
    // ratón encima. Usa ->limitWithTooltip(), el macro de AppServiceProvider.
    expect($offenders)->toBe([]);
});

it('no amontona más de dos píldoras visibles en una misma tabla', function (): void {
    $offenders = [];

    foreach (tableFiles() as $file) {
        $visibleBadges = 0;

        foreach (columnChunks(file_get_contents($file)) as $chunk) {
            if (! str_contains($chunk, '->badge()')) {
                continue;
            }

            if (str_contains($chunk, 'isToggledHiddenByDefault: true')) {
                continue;
            }

            $visibleBadges++;
        }

        if ($visibleBadges > 2) {
            $offenders[] = relativePath($file).' ('.$visibleBadges.' píldoras)';
        }
    }

    // Con cuatro píldoras de colores por fila ninguna destaca. La píldora se
    // reserva al estado; las escalas van como texto con color y las categorías
    // nominales como texto a secas.
    expect($offenders)->toBe([]);
});
