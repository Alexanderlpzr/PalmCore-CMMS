<?php

namespace App\Domain\Maintenance\Enums;

enum IssueReportStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case ConvertedToWO = 'converted_to_wo';
    case Resolved = 'resolved';
    // Legado: el reporte pasaba por una Solicitud de Mantenimiento, ya retirada del
    // flujo. Se conserva por los reportes viejos que quedaron con este estado.
    case ConvertedToMR = 'converted_to_mr';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierto',
            self::Acknowledged => 'Reconocido',
            self::ConvertedToWO => 'OT creada',
            self::Resolved => 'Resuelto',
            self::ConvertedToMR => 'Convertido a SM',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::Acknowledged => 'warning',
            self::ConvertedToWO => 'info',
            self::Resolved => 'success',
            self::ConvertedToMR => 'gray',
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
