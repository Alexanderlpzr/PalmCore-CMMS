<?php

namespace App\Domain\Energy\Enums;

/**
 * De dónde viene el kWh. Son tres y no una lista abierta porque cada uno responde una
 * pregunta distinta del informe:
 *
 *   - Red pública es lo que se paga a la electrificadora.
 *   - Planta eléctrica es lo que cuesta diésel.
 *   - Turbina es lo que la planta se genera a sí misma con su propio vapor, y es el
 *     numerador de «energía limpia».
 */
enum EnergySource: string
{
    case Grid = 'grid';
    case Genset = 'genset';
    case Turbine = 'turbine';

    public function label(): string
    {
        return match ($this) {
            self::Grid => 'Red pública',
            self::Genset => 'Planta eléctrica',
            self::Turbine => 'Turbina',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Grid => 'info',
            self::Genset => 'warning',
            self::Turbine => 'success',
        };
    }

    /** La turbina es la única fuente que no se compra ni se quema. */
    public function isClean(): bool
    {
        return $this === self::Turbine;
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
