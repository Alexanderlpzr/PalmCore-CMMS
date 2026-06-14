<?php

namespace App\Domain\Inventory\Enums;

enum SparePartUnit: string
{
    case Piece = 'pza';
    case Meter = 'm';
    case Meter2 = 'm2';
    case Liter = 'lt';
    case Gallon = 'gal';
    case Gram = 'g';
    case Kilo = 'kg';
    case Foot = 'ft';
    case Inch = 'in';
    case Pair = 'par';
    case Set = 'set';
    case Box = 'caja';
    case Roll = 'rollo';
    case Unit = 'unidad';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'Pieza',
            self::Meter => 'Metro',
            self::Meter2 => 'Metro cuadrado',
            self::Liter => 'Litro',
            self::Gallon => 'Galón',
            self::Gram => 'Gramo',
            self::Kilo => 'Kilogramo',
            self::Foot => 'Pie',
            self::Inch => 'Pulgada',
            self::Pair => 'Par',
            self::Set => 'Set/Juego',
            self::Box => 'Caja',
            self::Roll => 'Rollo',
            self::Unit => 'Unidad',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
