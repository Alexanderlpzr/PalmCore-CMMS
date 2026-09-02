<?php

use App\Domain\HumanResources\DTOs\ClassifiedHours;
use App\Domain\HumanResources\Services\HourClassifier;
use Carbon\CarbonImmutable;

/*
 * Lógica pura, sin base de datos: se puede probar exactamente el caso raro sin montar
 * empresa, empleado ni carné. Y el caso raro es el que importa, porque el turno de noche
 * que cruza a domingo es donde el Excel y el sentido común se separan.
 *
 * Agosto de 2026: domingos 2, 9, 16, 23 y 30; festivos 7 y 17.
 */

function clasificador(): HourClassifier
{
    return new HourClassifier;
}

/** Domingos y los dos festivos de agosto de 2026. */
function esDiaConRecargo(): callable
{
    $festivos = ['2026-08-07', '2026-08-17'];

    return fn (CarbonImmutable $fecha): bool => $fecha->isSunday()
        || in_array($fecha->toDateString(), $festivos, true);
}

function clasificar(string $desde, string $hasta, float $jornada = 8): ClassifiedHours
{
    return clasificador()->classify(
        [[CarbonImmutable::parse($desde), CarbonImmutable::parse($hasta)]],
        esDiaConRecargo(),
        nightWindowStart: 21,
        nightWindowEnd: 6,
        ordinaryHoursPerDay: $jornada,
    );
}

it('un turno diurno de día hábil no genera nada que pagar aparte', function (): void {
    // Lunes 10 de agosto, 06:00 a 14:00. Ocho horas ordinarias diurnas: ya van en el sueldo.
    $h = clasificar('2026-08-10 06:00', '2026-08-10 14:00');

    expect($h->ordinary)->toBe(8.0)
        ->and($h->workedHours())->toBe(8.0)
        ->and($h->overtimeHours())->toBe(0.0)
        ->and($h->nightSurcharge)->toBe(0.0);
});

it('paga recargo nocturno solo por las horas que caen dentro de la ventana', function (): void {
    // Martes 18:00 a 02:00. De 18:00 a 21:00 son 3 h diurnas; de 21:00 a 02:00, 5 h nocturnas.
    // Con jornada de 8 h no hay extras: son exactamente 8 h.
    $h = clasificar('2026-08-11 18:00', '2026-08-12 02:00');

    expect($h->ordinary)->toBe(3.0)
        ->and($h->nightSurcharge)->toBe(5.0)
        ->and($h->overtimeHours())->toBe(0.0);
});

it('reparte el turno que cruza a domingo entre día hábil y dominical', function (): void {
    // Sábado 22 de agosto 22:00 → domingo 23 a las 06:00.
    // Sábado 22:00–24:00 = 2 h nocturnas hábiles.
    // Domingo 00:00–06:00 = 6 h nocturnas dominicales.
    // Es el caso que en el Excel aparece anotado el día 22 y por eso se veían 44
    // registros dominicales en días que no son domingo.
    $h = clasificar('2026-08-22 22:00', '2026-08-23 06:00');

    expect($h->nightSurcharge)->toBe(2.0)
        ->and($h->nightSundaySurcharge)->toBe(6.0)
        ->and($h->sundaySurcharge)->toBe(0.0)
        ->and($h->overtimeHours())->toBe(0.0)
        ->and($h->workedHours())->toBe(8.0);
});

it('trata el festivo igual que el domingo', function (): void {
    // Viernes 7 de agosto es festivo. Turno diurno completo.
    $h = clasificar('2026-08-07 06:00', '2026-08-07 14:00');

    expect($h->sundaySurcharge)->toBe(8.0)
        ->and($h->ordinary)->toBe(0.0);
});

it('lo que pasa de la jornada ordinaria se vuelve hora extra', function (): void {
    // Lunes 06:00 a 16:00: 8 h ordinarias y 2 h extras, todas diurnas.
    $h = clasificar('2026-08-10 06:00', '2026-08-10 16:00');

    expect($h->ordinary)->toBe(8.0)
        ->and($h->overtimeDay)->toBe(2.0)
        ->and($h->overtimeHours())->toBe(2.0);
});

it('la hora extra hereda la condición de nocturna del momento en que ocurre', function (): void {
    // Lunes 14:00 a 23:00. Ordinarias 14:00–22:00 (de las cuales 21:00–22:00 nocturnas),
    // y la extra 22:00–23:00 cae en ventana nocturna.
    $h = clasificar('2026-08-10 14:00', '2026-08-10 23:00');

    expect($h->ordinary)->toBe(7.0)
        ->and($h->nightSurcharge)->toBe(1.0)
        ->and($h->overtimeNight)->toBe(1.0)
        ->and($h->overtimeDay)->toBe(0.0);
});

it('la extra que ocurre ya entrado el domingo se paga como extra dominical', function (): void {
    // Sábado 22:00 → domingo 08:00, diez horas.
    // Ordinarias (8 h): sábado 22:00–24:00 nocturnas hábiles + domingo 00:00–06:00 nocturnas dominicales.
    // Extras (2 h): domingo 06:00–08:00, ya fuera de la ventana nocturna → dominicales diurnas.
    $h = clasificar('2026-08-22 22:00', '2026-08-23 08:00');

    expect($h->nightSurcharge)->toBe(2.0)
        ->and($h->nightSundaySurcharge)->toBe(6.0)
        ->and($h->overtimeSundayDay)->toBe(2.0)
        ->and($h->overtimeSundayNight)->toBe(0.0)
        ->and($h->workedHours())->toBe(10.0);
});

it('acumula el tope de la jornada sobre todas las sesiones del día', function (): void {
    // Sale a almorzar y vuelve: 06:00–12:00 y 13:00–16:00. Nueve horas trabajadas, así
    // que la novena es extra. Quien sale a almorzar no reinicia su cuenta.
    $h = clasificador()->classify(
        [
            [CarbonImmutable::parse('2026-08-10 06:00'), CarbonImmutable::parse('2026-08-10 12:00')],
            [CarbonImmutable::parse('2026-08-10 13:00'), CarbonImmutable::parse('2026-08-10 16:00')],
        ],
        esDiaConRecargo(),
        nightWindowStart: 21,
        nightWindowEnd: 6,
        ordinaryHoursPerDay: 8,
    );

    expect($h->workedHours())->toBe(9.0)
        ->and($h->ordinary)->toBe(8.0)
        ->and($h->overtimeDay)->toBe(1.0);
});

it('respeta una ventana nocturna distinta sin tocar código', function (): void {
    // La reforma corre el inicio de la noche a las 19:00: la misma marca paga más horas.
    $conVentanaVieja = clasificar('2026-08-11 18:00', '2026-08-12 02:00');

    $conVentanaNueva = clasificador()->classify(
        [[CarbonImmutable::parse('2026-08-11 18:00'), CarbonImmutable::parse('2026-08-12 02:00')]],
        esDiaConRecargo(),
        nightWindowStart: 19,
        nightWindowEnd: 6,
        ordinaryHoursPerDay: 8,
    );

    expect($conVentanaVieja->nightSurcharge)->toBe(5.0)
        ->and($conVentanaNueva->nightSurcharge)->toBe(7.0)
        ->and($conVentanaNueva->ordinary)->toBe(1.0);
});

it('maneja los minutos sueltos sin perderlos', function (): void {
    // 06:00 a 14:37: ocho horas y treinta y siete minutos.
    $h = clasificar('2026-08-10 06:00', '2026-08-10 14:37');

    expect(round($h->workedHours(), 4))->toBe(8.6167)
        ->and($h->ordinary)->toBe(8.0)
        ->and(round($h->overtimeDay, 4))->toBe(0.6167);
});

it('ignora una sesión sin duración o invertida', function (): void {
    $h = clasificador()->classify(
        [
            [CarbonImmutable::parse('2026-08-10 06:00'), CarbonImmutable::parse('2026-08-10 06:00')],
            [CarbonImmutable::parse('2026-08-10 14:00'), CarbonImmutable::parse('2026-08-10 10:00')],
        ],
        esDiaConRecargo(),
        nightWindowStart: 21,
        nightWindowEnd: 6,
        ordinaryHoursPerDay: 8,
    );

    expect($h->workedHours())->toBe(0.0);
});

it('cubre el turno de veinticuatro horas sin dejar huecos ni contar dos veces', function (): void {
    // Guardia de domingo entero: 24 h que deben sumar exactamente 24.
    $h = clasificar('2026-08-23 00:00', '2026-08-24 00:00');

    expect($h->workedHours())->toBe(24.0)
        // 00:00–06:00 nocturnas dominicales = 6 h, todas dentro de las 8 ordinarias.
        ->and($h->nightSundaySurcharge)->toBe(6.0)
        // 06:00–08:00 dominicales diurnas ordinarias = 2 h.
        ->and($h->sundaySurcharge)->toBe(2.0)
        // 08:00–21:00 extras dominicales diurnas = 13 h.
        ->and($h->overtimeSundayDay)->toBe(13.0)
        // 21:00–24:00 extras dominicales nocturnas = 3 h.
        ->and($h->overtimeSundayNight)->toBe(3.0);
});
