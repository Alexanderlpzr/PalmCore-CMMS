<?php

namespace App\Domain\HumanResources\Enums;

/**
 * Cómo se lee y se escribe un parámetro.
 *
 * Existe por un error concreto y muy fácil de cometer: escribir 35 donde va 0,35. Un
 * recargo nocturno cargado como 35 multiplica por cien la nómina de la planta y el
 * número resultante es tan absurdo que nadie lo cree, pero el sistema sí lo pagaría.
 */
enum PayrollParameterUnit: string
{
    case Money = 'money';
    case Factor = 'factor';
    case Number = 'number';
    case HourOfDay = 'hour_of_day';

    public function label(): string
    {
        return match ($this) {
            self::Money => 'Pesos',
            self::Factor => 'Factor',
            self::Number => 'Número',
            self::HourOfDay => 'Hora del día',
        };
    }

    /** El techo con el que se valida la captura. */
    public function maxValue(): float
    {
        return match ($this) {
            self::Money => 999_999_999,
            self::Factor => 10,
            self::Number => 1_000,
            self::HourOfDay => 24,
        };
    }

    public function format(float $value): string
    {
        return match ($this) {
            self::Money => '$ '.number_format($value, 0, ',', '.'),
            self::Factor => number_format($value, 2, ',', '.'),
            self::Number => number_format($value, 0, ',', '.'),
            self::HourOfDay => sprintf('%02d:%02d', (int) $value, (int) round(fmod($value, 1) * 60)),
        };
    }
}
