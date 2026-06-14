<?php

namespace App\Domain\Webhooks\Enums;

enum WebhookEvent: string
{
    case AlertCreated = 'alert.created';
    case AlertResolved = 'alert.resolved';
    case WorkOrderCreated = 'work_order.created';
    case WorkOrderClosed = 'work_order.closed';
    case WorkOrderCompleted = 'work_order.completed';
    case MaintenanceRequestCreated = 'maintenance_request.created';
    case MaintenanceRequestApproved = 'maintenance_request.approved';
    case InventoryLowStock = 'inventory.low_stock';
    case AutomationExecuted = 'automation.executed';

    public function label(): string
    {
        return match ($this) {
            self::AlertCreated => 'Alerta creada',
            self::AlertResolved => 'Alerta resuelta',
            self::WorkOrderCreated => 'OT creada',
            self::WorkOrderClosed => 'OT cerrada',
            self::WorkOrderCompleted => 'OT completada',
            self::MaintenanceRequestCreated => 'Solicitud de mantenimiento creada',
            self::MaintenanceRequestApproved => 'Solicitud de mantenimiento aprobada',
            self::InventoryLowStock => 'Stock bajo punto de reorden',
            self::AutomationExecuted => 'Regla de automatización ejecutada',
        };
    }
}
