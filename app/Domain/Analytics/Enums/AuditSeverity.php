<?php

namespace App\Domain\Analytics\Enums;

/**
 * Severidad de un hallazgo de auditoría de datos. El valor es directamente el
 * color de Filament, para que la tarjeta lo use sin traducir.
 */
enum AuditSeverity: string
{
    case Critical = 'danger';
    case Warning = 'warning';
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Crítico',
            self::Warning => 'Advertencia',
            self::Info => 'Informativo',
        };
    }

    /** Orden para listar: lo crítico primero. */
    public function weight(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::Warning => 1,
            self::Info => 2,
        };
    }
}
