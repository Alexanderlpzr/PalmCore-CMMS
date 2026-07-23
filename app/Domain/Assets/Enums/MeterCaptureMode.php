<?php

namespace App\Domain\Assets\Enums;

/**
 * Qué significa el número que se captura para un equipo en la ronda diaria/semanal:
 *
 *  - Accumulated: es la lectura del cuenta-horas (horómetro), que solo sube. Las
 *    horas trabajadas del día = diferencia con la lectura anterior. Una lectura menor
 *    que la previa se toma como cambio de dial (reset).
 *  - DailyHours: es directamente las horas que la máquina trabajó ese día. No hay
 *    dial ni resta ni reset — el acumulado crece sumando esas horas.
 *
 * Los dos alimentan el mismo acumulado que usa el Control de Mantenimiento; solo
 * cambia cómo se interpreta lo que se teclea.
 */
enum MeterCaptureMode: string
{
    case Accumulated = 'accumulated';
    case DailyHours = 'daily_hours';

    public function label(): string
    {
        return match ($this) {
            self::Accumulated => 'Horómetro acumulado (cuenta-horas)',
            self::DailyHours => 'Horas trabajadas por día',
        };
    }

    public function isDailyHours(): bool
    {
        return $this === self::DailyHours;
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
