<?php

namespace App\Domain\HumanResources\Enums;

/**
 * Los tres tipos de bonificación, que no se distinguen por el nombre sino por si entran
 * al IBC.
 *
 * La constitutiva de salario suma a la base de aportes y a la de prestaciones; la no
 * constitutiva no suma a ninguna de las dos. En la nómina de agosto de la extractora eso
 * afecta a 35 de los 48 trabajadores y son 21 millones: es el segundo componente del
 * devengado, después del básico.
 */
enum BonusType: string
{
    case Vivienda = 'vivienda';
    case Constitutiva = 'constitutiva';
    case NoConstitutiva = 'no_constitutiva';

    public function label(): string
    {
        return match ($this) {
            self::Vivienda => 'Bonificación por vivienda',
            self::Constitutiva => 'Bonificación constitutiva de salario',
            self::NoConstitutiva => 'Bonificación no constitutiva',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Constitutiva => 'success',
            self::NoConstitutiva => 'gray',
            self::Vivienda => 'info',
        };
    }

    /** ¿Entra al IBC de salud y pensión? */
    public function countsIbc(): bool
    {
        return $this === self::Constitutiva;
    }

    /** ¿Entra a la base de prima y cesantías? */
    public function countsSeveranceBase(): bool
    {
        return $this === self::Constitutiva;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
