<?php

namespace App\Domain\Assets\Enums;

enum IssueSeverity: string
{
    case Low      = 'low';
    case Medium   = 'medium';
    case High     = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low      => 'Baja',
            self::Medium   => 'Media',
            self::High     => 'Alta',
            self::Critical => 'Crítica',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low      => 'success',
            self::Medium   => 'info',
            self::High     => 'warning',
            self::Critical => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
