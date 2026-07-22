<?php

namespace App\Domain\Maintenance\Enums;

enum WorkedHoursPeriodType: string
{
    case Diario = 'diario';
    case Semanal = 'semanal';

    public function label(): string
    {
        return match ($this) {
            self::Diario => 'Diario',
            self::Semanal => 'Semanal',
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
