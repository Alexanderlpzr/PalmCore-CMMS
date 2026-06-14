<?php

namespace App\Domain\Maintenance\Enums;

enum MaintenanceTriggerSource: string
{
    case Calendar = 'calendar';
    case Meter = 'meter';
    case Hybrid = 'hybrid';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Calendar => 'Por Calendario',
            self::Meter => 'Por Horómetro',
            self::Hybrid => 'Híbrido (tiempo + horas)',
            self::Manual => 'Manual',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Calendar => 'info',
            self::Meter => 'warning',
            self::Hybrid => 'success',
            self::Manual => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Calendar => 'heroicon-o-calendar',
            self::Meter => 'heroicon-o-clock',
            self::Hybrid => 'heroicon-o-arrows-right-left',
            self::Manual => 'heroicon-o-hand-raised',
        };
    }

    /** Time-based cadence uses fixed scheduling (anchored to the theoretical date). */
    public function usesFixedCadence(): bool
    {
        return $this === self::Calendar;
    }

    /** Meter-based cadence floats from the last real reading. */
    public function usesFloatingCadence(): bool
    {
        return $this === self::Meter;
    }

    public function requiresTimeFrequency(): bool
    {
        return in_array($this, [self::Calendar, self::Hybrid], strict: true);
    }

    public function requiresMeterInterval(): bool
    {
        return in_array($this, [self::Meter, self::Hybrid], strict: true);
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
