<?php

namespace App\Domain\Assets\Enums;

enum EquipmentCriticality: string
{
    case Critical = 'critical';
    case High     = 'high';
    case Medium   = 'medium';
    case Low      = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Crítico',
            self::High     => 'Alto',
            self::Medium   => 'Medio',
            self::Low      => 'Bajo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::High     => 'warning',
            self::Medium   => 'info',
            self::Low      => 'success',
        };
    }

    /** Used for sorting work orders and preventive maintenance queues. */
    public function score(): int
    {
        return match ($this) {
            self::Critical => 4,
            self::High     => 3,
            self::Medium   => 2,
            self::Low      => 1,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
