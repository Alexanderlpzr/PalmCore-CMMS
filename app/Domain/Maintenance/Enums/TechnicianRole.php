<?php

namespace App\Domain\Maintenance\Enums;

enum TechnicianRole: string
{
    case Lead       = 'lead';
    case Technician = 'technician';
    case Helper     = 'helper';

    public function label(): string
    {
        return match ($this) {
            self::Lead       => 'Técnico Líder',
            self::Technician => 'Técnico',
            self::Helper     => 'Ayudante',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Lead       => 'warning',
            self::Technician => 'info',
            self::Helper     => 'gray',
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
