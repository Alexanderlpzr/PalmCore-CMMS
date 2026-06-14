<?php

namespace App\Domain\Alerts\Enums;

enum AlertCategory: string
{
    case Inventory = 'inventory';
    case Reliability = 'reliability';
    case Maintenance = 'maintenance';
    case Automation = 'automation';
    case WorkOrder = 'work_order';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Inventory => 'Inventario',
            self::Reliability => 'Confiabilidad',
            self::Maintenance => 'Mantenimiento',
            self::Automation => 'Automatización',
            self::WorkOrder => 'Órdenes de Trabajo',
            self::System => 'Sistema',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Inventory => 'warning',
            self::Reliability => 'danger',
            self::Maintenance => 'info',
            self::Automation => 'purple',
            self::WorkOrder => 'orange',
            self::System => 'gray',
        };
    }
}
