<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\DTOs\ClassifiedHours;

/**
 * Parte las horas de un día entre lo que se paga como horas extras y recargos y lo que
 * se paga como bonificación constitutiva.
 *
 * Es la regla del formato de horas extras de la extractora (TH-FOR-002), leída en sus
 * periodos completos:
 *
 *  - Los días del corte que caen en el mes anterior —del 27 al último día— van enteros a
 *    bonificación, recargos y extras: ese mes ya se liquidó.
 *  - En los demás, las extras por encima del tope diario (dos horas) van a bonificación;
 *    las dos primeras y todos los recargos se pagan como horas extras y recargos.
 *  - El día que es extra entero —el domingo o festivo de descanso que alguien vino a
 *    trabajar— no lo parte el tope: en el formato va completo como extra dominical.
 *
 * El valor es el mismo de un lado y del otro, con los mismos factores. Lo que cambia es
 * el concepto con que se paga. Lógica pura, como `HourClassifier`.
 */
final class OvertimeBonusSplitter
{
    /**
     * El orden en que el tope llena las extras legales cuando un día trae de varias
     * clases. El día no guarda a qué hora cayó cada una, así que el orden es fijo: lo
     * que cambia es la clase con que se reporta, no el valor.
     */
    private const OVERTIME_ORDER = ['overtimeDay', 'overtimeNight', 'overtimeSundayDay', 'overtimeSundayNight'];

    /**
     * @param  bool  $wholeDayAsBonus  el día es del mes anterior al que se liquida
     * @param  float  $dailyCap  las horas extras por día que se pagan como extras
     * @param  bool  $isRestDay  talento humano lo marcó como descanso trabajado
     * @return array{legal: ClassifiedHours, bonus: ClassifiedHours}
     */
    public function split(ClassifiedHours $hours, bool $wholeDayAsBonus, float $dailyCap, bool $isRestDay = false): array
    {
        if ($wholeDayAsBonus) {
            return [
                'legal' => new ClassifiedHours(ordinary: $hours->ordinary),
                'bonus' => new ClassifiedHours(
                    nightSurcharge: $hours->nightSurcharge,
                    sundaySurcharge: $hours->sundaySurcharge,
                    nightSundaySurcharge: $hours->nightSundaySurcharge,
                    overtimeDay: $hours->overtimeDay,
                    overtimeNight: $hours->overtimeNight,
                    overtimeSundayDay: $hours->overtimeSundayDay,
                    overtimeSundayNight: $hours->overtimeSundayNight,
                ),
            ];
        }

        $hasOrdinaryShift = ($hours->ordinary + $hours->nightSurcharge + $hours->sundaySurcharge + $hours->nightSundaySurcharge) > 0;

        if ($isRestDay || ! $hasOrdinaryShift) {
            return ['legal' => $hours, 'bonus' => ClassifiedHours::empty()];
        }

        $remaining = max(0.0, $dailyCap);
        $legal = [];
        $bonus = [];

        foreach (self::OVERTIME_ORDER as $field) {
            $taken = min($hours->{$field}, $remaining);
            $legal[$field] = $taken;
            $bonus[$field] = $hours->{$field} - $taken;
            $remaining -= $taken;
        }

        return [
            'legal' => new ClassifiedHours(...[
                'ordinary' => $hours->ordinary,
                'nightSurcharge' => $hours->nightSurcharge,
                'sundaySurcharge' => $hours->sundaySurcharge,
                'nightSundaySurcharge' => $hours->nightSundaySurcharge,
                ...$legal,
            ]),
            'bonus' => new ClassifiedHours(...$bonus),
        ];
    }
}
