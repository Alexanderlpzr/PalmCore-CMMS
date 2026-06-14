<?php

namespace App\Domain\Maintenance\Enums;

enum MaintenanceChecklistItemType: string
{
    case Boolean = 'boolean';
    case Numeric = 'numeric';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::Boolean => 'Sí / No',
            self::Numeric => 'Numérico',
            self::Text => 'Texto',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Boolean => 'heroicon-o-check-circle',
            self::Numeric => 'heroicon-o-calculator',
            self::Text => 'heroicon-o-chat-bubble-left',
        };
    }

    /** Whether this type can have min/max range validation. */
    public function hasRange(): bool
    {
        return $this === self::Numeric;
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
