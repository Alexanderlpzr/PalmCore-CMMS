<?php

namespace App\Domain\Commercial\Enums;

/**
 * Las dos versiones del plan de un año. El original se aprueba y queda congelado: es lo
 * que se esperaba al empezar. La reproyección nace de él al aprobarlo y se ajusta cuando
 * el año cambia; es la que manda en el cuadro cruce.
 */
enum PlanVersion: string
{
    case Original = 'original';
    case Reproyeccion = 'reproyeccion';

    public function label(): string
    {
        return match ($this) {
            self::Original => 'Plan original',
            self::Reproyeccion => 'Reproyección',
        };
    }
}
