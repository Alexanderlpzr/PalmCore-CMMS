<?php

namespace App\Domain\Assets\Enums;

/**
 * Con qué cadencia la planta toma la lectura del horómetro de un equipo, tal como
 * lo separa el Excel: unos equipos se leen todos los días (Registro Diario), otros
 * una vez por semana (Registro Semanal). Determina en cuál de los dos módulos de
 * captura aparece el equipo.
 *
 * Nullable en la base: un equipo sin frecuencia no entra a ninguna ronda de
 * lecturas — no todos los activos llevan horómetro.
 */
enum MeterReadingFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Diario',
            self::Weekly => 'Semanal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Daily => 'info',
            self::Weekly => 'warning',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $options, self $case): array => [...$options, $case->value => $case->label()],
            [],
        );
    }
}
