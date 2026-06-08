<?php

namespace App\Domain\Maintenance\Enums;

enum IssueReportStatus: string
{
    case Open            = 'open';
    case Acknowledged    = 'acknowledged';
    case ConvertedToMR   = 'converted_to_mr';

    public function label(): string
    {
        return match ($this) {
            self::Open          => 'Abierto',
            self::Acknowledged  => 'Reconocido',
            self::ConvertedToMR => 'Convertido a SM',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open          => 'danger',
            self::Acknowledged  => 'warning',
            self::ConvertedToMR => 'success',
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
