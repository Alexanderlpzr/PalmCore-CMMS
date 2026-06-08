<?php

namespace App\Domain\Shared\Enums;

enum HealthStatus: string
{
    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';

    public function label(): string
    {
        return match($this) {
            self::Green => 'Operativo',
            self::Yellow => 'Atención',
            self::Red => 'Crítico',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Green => '#22c55e',
            self::Yellow => '#eab308',
            self::Red => '#ef4444',
        };
    }
}
