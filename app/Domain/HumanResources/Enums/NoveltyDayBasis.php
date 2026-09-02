<?php

namespace App\Domain\HumanResources\Enums;

/**
 * Sobre qué se calcula el valor del día de una novedad.
 */
enum NoveltyDayBasis: string
{
    /** El valor día del propio salario del trabajador. */
    case OwnSalary = 'own_salary';

    /** El valor día del salario mínimo: el piso de la incapacidad por enfermedad general. */
    case Smlmv = 'smlmv';

    /** El día no se paga. */
    case Unpaid = 'unpaid';

    public function label(): string
    {
        return match ($this) {
            self::OwnSalary => 'Valor día del salario',
            self::Smlmv => 'Valor día del mínimo',
            self::Unpaid => 'No se paga',
        };
    }
}
