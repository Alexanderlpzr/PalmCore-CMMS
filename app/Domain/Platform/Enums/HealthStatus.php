<?php

namespace App\Domain\Platform\Enums;

/**
 * `Unknown` existe a propósito y no es un adorno.
 *
 * Un chequeo que no se pudo ejecutar —Redis apagado, disco inaccesible— no está bien
 * ni mal: no se sabe. Pintarlo de verde sería exactamente la mentira que un panel de
 * salud no se puede permitir, porque su único trabajo es que confíes en él.
 */
enum HealthStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Critical = 'critical';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Correcto',
            self::Warning => 'Atención',
            self::Critical => 'Crítico',
            self::Unknown => 'Sin datos',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::Warning => 'warning',
            self::Critical => 'danger',
            self::Unknown => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Ok => 'heroicon-o-check-circle',
            self::Warning => 'heroicon-o-exclamation-triangle',
            self::Critical => 'heroicon-o-x-circle',
            self::Unknown => 'heroicon-o-question-mark-circle',
        };
    }
}
