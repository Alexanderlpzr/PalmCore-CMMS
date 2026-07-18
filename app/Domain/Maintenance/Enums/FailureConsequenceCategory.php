<?php

namespace App\Domain\Maintenance\Enums;

/**
 * The 4 RCM consequence categories (SAE JA1011): what happens when a
 * failure mode occurs decides which default action applies. Hidden failures
 * in particular need a failure-finding task, since nobody notices them
 * during normal operation.
 */
enum FailureConsequenceCategory: string
{
    case SafetyEnvironmental = 'safety_environmental';
    case Operational = 'operational';
    case NonOperational = 'non_operational';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::SafetyEnvironmental => 'Seguridad / ambiental',
            self::Operational => 'Operacional',
            self::NonOperational => 'No operacional',
            self::Hidden => 'Oculta',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SafetyEnvironmental => 'danger',
            self::Operational => 'warning',
            self::NonOperational => 'info',
            self::Hidden => 'gray',
        };
    }

    /** @return array<string, string> value => label, for Filament selects. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
