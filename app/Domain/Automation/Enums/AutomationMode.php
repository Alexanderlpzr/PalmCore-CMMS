<?php

namespace App\Domain\Automation\Enums;

enum AutomationMode: string
{
    case Disabled = 'disabled';
    case NotifyOnly = 'notify_only';
    case Automatic = 'automatic';

    public function label(): string
    {
        return match ($this) {
            self::Disabled => 'Desactivado',
            self::NotifyOnly => 'Solo notificar',
            self::Automatic => 'Automático',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Disabled => 'gray',
            self::NotifyOnly => 'warning',
            self::Automatic => 'success',
        };
    }
}
