<?php

namespace App\Domain\HumanResources\Enums;

/**
 * Por qué se anuló un carné al reemitirlo. Queda en el carné viejo, con quién y cuándo,
 * para que el historial del trabajador diga algo más que «hubo otro».
 */
enum QrRevocationReason: string
{
    case Perdido = 'perdido';
    case Danado = 'danado';
    case Robado = 'robado';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Perdido => 'Perdido',
            self::Danado => 'Dañado',
            self::Robado => 'Robado',
            self::Otro => 'Otro',
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
