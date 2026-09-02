<?php

namespace App\Domain\HumanResources\Enums;

/**
 * Si las horas del día ya las revisó alguien.
 *
 * El reloj propone; una persona confirma. Es la propiedad que conserva lo mejor del libro
 * de Excel —que un humano clasifica cada hora antes de que se pague— sin conservar lo
 * peor, que es que ese humano tenga además que calcularla.
 *
 * Solo lo confirmado entra a la liquidación. Una propuesta se puede reconstruir cuantas
 * veces haga falta; una confirmada, no: eso borraría la revisión de alguien.
 */
enum AttendanceDayStatus: string
{
    case Propuesta = 'propuesta';
    case Confirmada = 'confirmada';

    public function label(): string
    {
        return match ($this) {
            self::Propuesta => 'Propuesta',
            self::Confirmada => 'Confirmada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Propuesta => 'warning',
            self::Confirmada => 'success',
        };
    }

    /** Solo lo confirmado se paga. */
    public function isPayable(): bool
    {
        return $this === self::Confirmada;
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
