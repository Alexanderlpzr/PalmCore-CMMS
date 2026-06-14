<?php

namespace App\Domain\Reports\Enums;

enum ReportType: string
{
    case WorkOrder = 'work_order';
    case EquipmentSheet = 'equipment_sheet';
    case MaintenancePlan = 'maintenance_plan';
    case Inventory = 'inventory';
    case Reliability = 'reliability';
}
