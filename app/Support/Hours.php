<?php

namespace App\Support;

/**
 * Las horas como se leen en la planta: «12 h», «5 h 30 min», «45 min». Cero no se escribe:
 * en una tabla de recargos y extras, una fila de ceros esconde las horas que sí hay.
 */
final class Hours
{
    public static function format(float|int|string|null $hours): string
    {
        $minutes = (int) round(((float) $hours) * 60);

        if ($minutes <= 0) {
            return '';
        }

        $whole = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $whole === 0 => "{$rest} min",
            $rest === 0 => "{$whole} h",
            default => "{$whole} h {$rest} min",
        };
    }
}
