<?php

namespace App\Domain\Assets\Enums;

use App\Domain\Maintenance\Enums\WorkOrderType;

enum EquipmentDowntimeCauseType: string
{
    case Corrective = 'corrective';
    case Preventive = 'preventive';
    case Emergency = 'emergency';
    case External = 'external';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Corrective => 'Correctivo',
            self::Preventive => 'Preventivo',
            self::Emergency => 'Emergencia',
            self::External => 'Externo',
            self::Other => 'Otro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Corrective => 'warning',
            self::Preventive => 'info',
            self::Emergency => 'danger',
            self::External => 'gray',
            self::Other => 'gray',
        };
    }

    public function wasPlanned(): bool
    {
        return $this === self::Preventive;
    }

    public static function fromWorkOrderType(WorkOrderType $type): self
    {
        return match ($type) {
            WorkOrderType::Corrective => self::Corrective,
            WorkOrderType::Preventive => self::Preventive,
            WorkOrderType::Emergency => self::Emergency,
            default => self::Other,
        };
    }
}
