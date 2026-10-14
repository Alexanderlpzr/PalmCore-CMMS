<?php

namespace App\Domain\HumanResources\Support;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Models\AttendanceDay;

/**
 * El estado de un día como se ve en todo el módulo: el mismo nombre y el mismo color en
 * la cuadrícula de la semana, el calendario de la ficha y las tablas.
 *
 * Amarillo por confirmar, rojo con avisos, verde confirmado y azul ajustado a mano.
 */
final class DayTone
{
    public const LABELS = [
        'pending' => 'Por confirmar',
        'warning' => 'Con avisos',
        'confirmed' => 'Confirmado',
        'adjusted' => 'Ajustado a mano',
    ];

    /** El color de Filament de cada estado. */
    public const COLORS = [
        'pending' => 'warning',
        'warning' => 'danger',
        'confirmed' => 'success',
        'adjusted' => 'info',
    ];

    public static function of(AttendanceDay $day): string
    {
        return match (true) {
            $day->status === AttendanceDayStatus::Confirmada && $day->isManuallyAdjusted() => 'adjusted',
            $day->status === AttendanceDayStatus::Confirmada => 'confirmed',
            $day->hasAnomalies() => 'warning',
            $day->isManuallyAdjusted() => 'adjusted',
            default => 'pending',
        };
    }

    public static function label(AttendanceDay $day): string
    {
        return self::LABELS[self::of($day)];
    }

    public static function color(AttendanceDay $day): string
    {
        return self::COLORS[self::of($day)];
    }
}
