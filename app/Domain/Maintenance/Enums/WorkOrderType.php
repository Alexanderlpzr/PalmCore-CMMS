<?php

namespace App\Domain\Maintenance\Enums;

enum WorkOrderType: string
{
    case Corrective  = 'corrective';
    case Preventive  = 'preventive';
    case Predictive  = 'predictive';
    case Improvement = 'improvement';
    case Emergency   = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::Corrective  => 'Correctivo',
            self::Preventive  => 'Preventivo',
            self::Predictive  => 'Predictivo',
            self::Improvement => 'Mejora',
            self::Emergency   => 'Emergencia',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Corrective  => 'warning',
            self::Preventive  => 'info',
            self::Predictive  => 'success',
            self::Improvement => 'gray',
            self::Emergency   => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Corrective  => 'heroicon-o-wrench',
            self::Preventive  => 'heroicon-o-calendar',
            self::Predictive  => 'heroicon-o-chart-bar',
            self::Improvement => 'heroicon-o-arrow-trending-up',
            self::Emergency   => 'heroicon-o-exclamation-triangle',
        };
    }

    /** Emergency WOs skip draft/planned and start directly in_progress. */
    public function startsInProgress(): bool
    {
        return $this === self::Emergency;
    }

    public static function fromMaintenanceRequestType(MaintenanceRequestType $type): self
    {
        return self::from($type->value);
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
