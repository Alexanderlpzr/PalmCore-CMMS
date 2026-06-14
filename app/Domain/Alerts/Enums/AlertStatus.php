<?php

namespace App\Domain\Alerts\Enums;

enum AlertStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::Resolved => 'Resuelta',
            self::Dismissed => 'Descartada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::Resolved => 'success',
            self::Dismissed => 'gray',
        };
    }

    public function isClosed(): bool
    {
        return $this !== self::Open;
    }
}
