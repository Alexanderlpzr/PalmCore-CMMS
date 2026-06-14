<?php

namespace App\Domain\Maintenance\Enums;

enum MeterReadingUnit: string
{
    case Hours = 'hours';
    case Cycles = 'cycles';
    case Km = 'km';

    public function label(): string
    {
        return match ($this) {
            self::Hours => 'Horas',
            self::Cycles => 'Ciclos',
            self::Km => 'Kilómetros',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Hours => 'h',
            self::Cycles => 'ciclos',
            self::Km => 'km',
        };
    }

    public static function options(): array
    {
        return array_column(
            array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value'
        );
    }
}
