<?php

use App\Domain\HumanResources\Services\NoveltyWindowPlanner;

/*
 * Lógica pura de colocación. Existe porque dos discrepancias del paralelo contra el libro
 * de agosto salieron justamente de aquí: poner las novedades al final del mes sin mirar
 * nada pisaba días que la hoja diaria registraba con horas extras, y esas horas
 * desaparecían del cálculo.
 */

function planner(): NoveltyWindowPlanner
{
    return new NoveltyWindowPlanner;
}

it('coloca el bloque al final cuando no hay horas que respetar', function (): void {
    // Sin información que contradecir, gana la ventana más tardía: es donde el libro las
    // tiene y donde menos se nota la invención.
    expect(planner()->place(days: 6, periodDays: 30, taken: [], extraHoursByDay: []))->toBe(24);
});

it('evita los días que la hoja diaria registra con horas extras', function (): void {
    // El último tramo del mes tiene horas; el bloque debe correrse hacia atrás.
    $horas = [26 => 4.0, 27 => 6.0, 28 => 2.0, 29 => 8.0, 30 => 3.0];

    $offset = planner()->place(days: 5, periodDays: 30, taken: [], extraHoursByDay: $horas);

    // Base 0: offset 20 son los días 21 al 25, todos limpios.
    expect($offset)->toBe(20);
});

it('elige el tramo que pisa menos horas cuando todos pisan algo', function (): void {
    // Un mes corto donde no hay ninguna ventana limpia: gana la de menor coste.
    $horas = [1 => 8.0, 2 => 8.0, 3 => 1.0, 4 => 1.0, 5 => 8.0];

    $offset = planner()->place(days: 2, periodDays: 5, taken: [], extraHoursByDay: $horas);

    // Días 3 y 4, que suman 2 horas: cualquier otra ventana suma más.
    expect($offset)->toBe(2);
});

it('no pisa un tramo que ya ocupó otra novedad', function (): void {
    // Los últimos ocho días están tomados por unas vacaciones ya colocadas.
    $taken = array_fill_keys(range(22, 29), true);

    $offset = planner()->place(days: 3, periodDays: 30, taken: $taken, extraHoursByDay: []);

    expect($offset)->toBe(19)
        ->and($offset + 3)->toBeLessThanOrEqual(22);
});

it('devuelve null cuando ya no cabe', function (): void {
    $taken = array_fill_keys(range(0, 29), true);

    expect(planner()->place(days: 1, periodDays: 30, taken: $taken, extraHoursByDay: []))->toBeNull();
});

it('devuelve null si el bloque es más largo que el período', function (): void {
    expect(planner()->place(days: 31, periodDays: 30, taken: [], extraHoursByDay: []))->toBeNull()
        ->and(planner()->place(days: 0, periodDays: 30, taken: [], extraHoursByDay: []))->toBeNull();
});

it('cabe exactamente cuando el bloque ocupa todo el período', function (): void {
    // El caso del trabajador que estuvo los treinta días incapacitado.
    expect(planner()->place(days: 30, periodDays: 30, taken: [], extraHoursByDay: []))->toBe(0);
});

it('coloca varios bloques sin solaparlos', function (): void {
    $planner = planner();
    $taken = [];
    $colocados = [];

    foreach ([6, 3, 2] as $days) {
        $offset = $planner->place($days, 30, $taken, []);

        expect($offset)->not->toBeNull();

        for ($i = 0; $i < $days; $i++) {
            expect($taken)->not->toHaveKey($offset + $i);
            $taken[$offset + $i] = true;
        }

        $colocados[] = [$offset, $days];
    }

    expect($colocados)->toHaveCount(3)
        ->and(count($taken))->toBe(11);
});
