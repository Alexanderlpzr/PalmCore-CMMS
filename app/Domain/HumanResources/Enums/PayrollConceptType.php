<?php

namespace App\Domain\HumanResources\Enums;

/**
 * Si el concepto suma o resta en el desprendible.
 */
enum PayrollConceptType: string
{
    case Devengado = 'devengado';
    case Deduccion = 'deduccion';

    public function label(): string
    {
        return match ($this) {
            self::Devengado => 'Devengado',
            self::Deduccion => 'Deducción',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Devengado => 'success',
            self::Deduccion => 'danger',
        };
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
