<?php

namespace App\Domain\Reports\Enums;

enum ExcelReportType: string
{
    case Inventory = 'inventory';
    case Reliability = 'reliability';
    case WorkOrders = 'work_orders';
    case DowntimeEvents = 'downtime_events';
    case MaintenancePlans = 'maintenance_plans';

    public function label(): string
    {
        return match ($this) {
            self::Inventory => 'Inventario',
            self::Reliability => 'Confiabilidad',
            self::WorkOrders => 'Órdenes de Trabajo',
            self::DowntimeEvents => 'Eventos de Parada',
            self::MaintenancePlans => 'Planes de Mantenimiento',
        };
    }

    public function filename(): string
    {
        $date = now()->format('Ymd-His');

        return match ($this) {
            self::Inventory => "INV-{$date}.xlsx",
            self::Reliability => "CONFIABILIDAD-{$date}.xlsx",
            self::WorkOrders => "OT-{$date}.xlsx",
            self::DowntimeEvents => "PARADAS-{$date}.xlsx",
            self::MaintenancePlans => "PM-{$date}.xlsx",
        };
    }
}
