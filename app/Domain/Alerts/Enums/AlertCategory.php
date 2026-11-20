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
    /** Los hallazgos de SST y Ambiental que esperan a mantenimiento: su propio menú (2026-11-19). */
    case Safety = 'safety';

    public function label(): string
    {
        return match ($this) {
            self::Inventory => 'Inventario',
            self::Reliability => 'Confiabilidad',
            self::Maintenance => 'Mantenimiento',
            self::Automation => 'Automatización',
            self::WorkOrder => 'Órdenes de Trabajo',
            self::System => 'Sistema',
            self::Safety => 'SST y Ambiental',
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
            self::Safety => 'success',
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
