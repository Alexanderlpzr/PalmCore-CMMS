<?php

namespace App\Domain\Maintenance\Enums;

enum MaintenanceRequestType: string
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

    /** Emergency requests bypass the draft/submitted states and start under_review. */
    public function startsUnderReview(): bool
    {
        return $this === self::Emergency;
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
