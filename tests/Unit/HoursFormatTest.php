<?php

use App\Support\Hours;

/*
 * Las horas como se leen en la planta: «12 h», «5 h 30 min», «45 min», y nada cuando es
 * cero, para que una fila de recargos no se llene de ceros.
 */

it('writes hours the way the plant reads them', function (float|string|null $horas, string $texto): void {
    expect(Hours::format($horas))->toBe($texto);
})->with([
    [12.0, '12 h'],
    [5.5, '5 h 30 min'],
    [0.75, '45 min'],
    ['7.3333', '7 h 20 min'],
    [0.0, ''],
    [null, ''],
]);
