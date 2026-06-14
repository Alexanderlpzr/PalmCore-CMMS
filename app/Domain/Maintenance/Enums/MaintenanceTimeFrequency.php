<?php

namespace App\Domain\Maintenance\Enums;

use Carbon\CarbonInterface;

enum MaintenanceTimeFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannual = 'semiannual';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Diario',
            self::Weekly => 'Semanal',
            self::Monthly => 'Mensual',
            self::Quarterly => 'Trimestral',
            self::Semiannual => 'Semestral',
            self::Annual => 'Anual',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Daily => 'DIARIO',
            self::Weekly => 'SEMANAL',
            self::Monthly => 'MENSUAL',
            self::Quarterly => 'TRIMESTRAL',
            self::Semiannual => 'SEMESTRAL',
            self::Annual => 'ANUAL',
        };
    }

    /** Returns approximate days in this period (for overdue estimation). */
    public function approximateDays(): int
    {
        return match ($this) {
            self::Daily => 1,
            self::Weekly => 7,
            self::Monthly => 30,
            self::Quarterly => 90,
            self::Semiannual => 180,
            self::Annual => 365,
        };
    }

    /** Add this frequency to a Carbon date. */
    public function addTo(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::Daily => $date->addDay(),
            self::Weekly => $date->addWeek(),
            self::Monthly => $date->addMonth(),
            self::Quarterly => $date->addMonths(3),
            self::Semiannual => $date->addMonths(6),
            self::Annual => $date->addYear(),
        };
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
