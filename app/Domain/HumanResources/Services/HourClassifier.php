<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\DTOs\ClassifiedHours;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Convierte «entró a las 22:00 del sábado y salió a las 6:00 del domingo» en las siete
 * bolsas de horas que paga la nómina.
 *
 * Es la pieza que el libro de Excel no tiene: allí un humano mira la planilla y decide
 * que cuatro horas fueron recargo nocturno y dos extra dominical nocturna. Aquí eso se
 * deduce, y por eso esta clase es lógica pura —sin base de datos, sin modelos, sin
 * tenant— y toda la configuración entra por parámetros. Es la única forma de poder
 * probarla contra los casos raros, que son los que importan.
 *
 * El método es partir el turno en tramos por cada frontera que cambia la clasificación:
 * la medianoche (cambia el día, y con él si es domingo o festivo), el inicio de la
 * jornada nocturna y su fin. Dentro de un tramo la clasificación es constante, así que
 * basta mirar su punto medio. Después se corta otra vez donde el acumulado del día cruza
 * la jornada ordinaria, que es la frontera entre hora ordinaria y hora extra.
 *
 * Un turno que cruza medianoche reparte sus horas entre dos días distintos, y esa es
 * justamente la razón de existir del clasificador: en la nómina de agosto de la
 * extractora hay 44 registros de recargo nocturno dominical anotados los días 6, 8, 15 y
 * 22 —vísperas de domingo o festivo— porque quien captura escribe la hora en el día en
 * que arrancó el turno. Aquí las horas caen en el día que de verdad les corresponde para
 * efectos del recargo, aunque el jornal se siga atribuyendo al día en que se entró.
 */
class HourClassifier
{
    /**
     * Reparte las horas de un día de trabajo.
     *
     * @param  array<int, array{0: CarbonInterface, 1: CarbonInterface}>  $sessions  Pares entrada/salida.
     *                                                                               Van juntos porque el tope de la
     *                                                                               jornada ordinaria se acumula sobre
     *                                                                               todo lo trabajado en el día: quien
     *                                                                               sale a almorzar y vuelve no reinicia
     *                                                                               su cuenta de horas extras.
     * @param  callable(CarbonImmutable): bool  $isSurchargedDay  Si esa fecha se paga con recargo dominical.
     *                                                            Domingo o festivo, que legalmente valen igual.
     */
    public function classify(
        array $sessions,
        callable $isSurchargedDay,
        float $nightWindowStart,
        float $nightWindowEnd,
        float $ordinaryHoursPerDay,
    ): ClassifiedHours {
        $nightStartMin = (int) round($nightWindowStart * 60);
        $nightEndMin = (int) round($nightWindowEnd * 60);
        $ordinaryMin = (int) round($ordinaryHoursPerDay * 60);

        // Buckets en minutos: se acumula en enteros y se divide una sola vez al final,
        // para que sumar tramos no arrastre error de coma flotante.
        $bucket = [
            'ordinary' => 0, 'night' => 0, 'sunday' => 0, 'nightSunday' => 0,
            'otDay' => 0, 'otNight' => 0, 'otSundayDay' => 0, 'otSundayNight' => 0,
        ];

        $surchargedCache = [];
        $workedMinutes = 0;

        foreach ($sessions as [$start, $end]) {
            $start = CarbonImmutable::instance($start);
            $end = CarbonImmutable::instance($end);

            if ($end <= $start) {
                continue;
            }

            foreach ($this->segments($start, $end, $nightStartMin, $nightEndMin) as [$from, $to]) {
                $minutes = (int) round($from->diffInMinutes($to, absolute: true));

                if ($minutes <= 0) {
                    continue;
                }

                // El punto medio del tramo: dentro de un tramo la clasificación no cambia,
                // y el medio nunca cae justo sobre una frontera.
                $mid = $from->addMinutes($minutes / 2);
                $isNight = $this->isNightMinute($mid->hour * 60 + $mid->minute, $nightStartMin, $nightEndMin);

                $dateKey = $mid->toDateString();
                $isSunday = $surchargedCache[$dateKey] ??= $isSurchargedDay($mid->startOfDay());

                // El tramo puede quedar partido por el tope de la jornada ordinaria.
                $ordinaryPart = max(0, min($minutes, $ordinaryMin - $workedMinutes));
                $overtimePart = $minutes - $ordinaryPart;
                $workedMinutes += $minutes;

                if ($ordinaryPart > 0) {
                    $key = match (true) {
                        $isSunday && $isNight => 'nightSunday',
                        $isSunday => 'sunday',
                        $isNight => 'night',
                        // Ordinaria diurna en día hábil: no se paga aparte, ya va en el salario.
                        default => 'ordinary',
                    };
                    $bucket[$key] += $ordinaryPart;
                }

                if ($overtimePart > 0) {
                    $key = match (true) {
                        $isSunday && $isNight => 'otSundayNight',
                        $isSunday => 'otSundayDay',
                        $isNight => 'otNight',
                        default => 'otDay',
                    };
                    $bucket[$key] += $overtimePart;
                }
            }
        }

        return new ClassifiedHours(
            ordinary: $bucket['ordinary'] / 60,
            nightSurcharge: $bucket['night'] / 60,
            sundaySurcharge: $bucket['sunday'] / 60,
            nightSundaySurcharge: $bucket['nightSunday'] / 60,
            overtimeDay: $bucket['otDay'] / 60,
            overtimeNight: $bucket['otNight'] / 60,
            overtimeSundayDay: $bucket['otSundayDay'] / 60,
            overtimeSundayNight: $bucket['otSundayNight'] / 60,
        );
    }

    /**
     * Parte el turno en tramos de clasificación constante.
     *
     * Las fronteras son tres por cada día que toca el turno: la medianoche, el inicio de
     * la jornada nocturna y su fin. Se generan todas, se descartan las que caen fuera y
     * se ordenan; entre dos consecutivas nada cambia.
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function segments(
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $nightStartMin,
        int $nightEndMin,
    ): array {
        $boundaries = [$start, $end];

        $day = $start->startOfDay();
        $lastDay = $end->startOfDay();

        // `addDay()` y no un bucle sobre horas: un turno nunca cruza más de dos o tres
        // medianoches, pero si alguien registra una marca absurda el bucle igual termina.
        while ($day <= $lastDay) {
            $boundaries[] = $day;
            $boundaries[] = $day->addMinutes($nightStartMin);
            $boundaries[] = $day->addMinutes($nightEndMin);
            $day = $day->addDay();
        }

        $boundaries[] = $day; // la medianoche siguiente al último día

        $inside = array_filter(
            $boundaries,
            fn (CarbonImmutable $b): bool => $b >= $start && $b <= $end,
        );

        $unique = [];
        foreach ($inside as $b) {
            $unique[$b->getTimestamp()] = $b;
        }

        ksort($unique);
        $points = array_values($unique);

        $segments = [];
        for ($i = 0; $i < count($points) - 1; $i++) {
            $segments[] = [$points[$i], $points[$i + 1]];
        }

        return $segments;
    }

    /**
     * ¿Este minuto del día cae en jornada nocturna?
     *
     * La ventana normalmente envuelve la medianoche (21:00 a 06:00), así que la
     * comparación es una unión y no un rango. Se contempla también la ventana que no
     * envuelve, por si alguna vez la norma la deja dentro del mismo día.
     */
    private function isNightMinute(int $minuteOfDay, int $nightStartMin, int $nightEndMin): bool
    {
        if ($nightStartMin === $nightEndMin) {
            return false;
        }

        return $nightStartMin > $nightEndMin
            ? ($minuteOfDay >= $nightStartMin || $minuteOfDay < $nightEndMin)
            : ($minuteOfDay >= $nightStartMin && $minuteOfDay < $nightEndMin);
    }
}
