<?php

namespace App\Domain\HumanResources\Enums;

/**
 * Si el escaneo fue de entrada o de salida.
 *
 * Portería no lo elige: lo deduce el sistema del último escaneo del empleado. Pedirle al
 * vigilante que además acierte el botón, con lluvia y con quince personas en la puerta,
 * es garantizar el error. Pero la dirección deducida se guarda resuelta, porque dentro
 * de un año nadie va a poder reconstruir qué se dedujo entonces si solo quedan las
 * marcas sueltas.
 */
enum AttendanceDirection: string
{
    case Entrada = 'entrada';
    case Salida = 'salida';

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Salida => 'Salida',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Entrada => 'success',
            self::Salida => 'info',
        };
    }

    public function opposite(): self
    {
        return match ($this) {
            self::Entrada => self::Salida,
            self::Salida => self::Entrada,
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
