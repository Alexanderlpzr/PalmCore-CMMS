<?php

namespace App\Domain\Assets\Enums;

enum ComponentStatus: string
{
    case Active = 'active';
    case Degraded = 'degraded';
    case Failed = 'failed';
    case Replaced = 'replaced';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Operativo',
            self::Degraded => 'Degradado',
            self::Failed => 'Falla',
            self::Replaced => 'Reemplazado',
            self::Retired => 'Retirado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'emerald',
            self::Degraded => 'amber',
            self::Failed => 'red',
            self::Replaced => 'blue',
            self::Retired => 'gray',
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
