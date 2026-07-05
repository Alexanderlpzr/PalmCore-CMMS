<?php

namespace App\Domain\Alerts\Enums;

enum AlertSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Informativo',
            self::Warning => 'Advertencia',
            self::Critical => 'Crítico',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Warning => 'warning',
            self::Critical => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Info => 'heroicon-o-information-circle',
            self::Warning => 'heroicon-o-exclamation-triangle',
            self::Critical => 'heroicon-o-exclamation-circle',
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
