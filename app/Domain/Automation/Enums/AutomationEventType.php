<?php

namespace App\Domain\Automation\Enums;

enum AutomationEventType: string
{
    case MaintenancePlanOverdue = 'maintenance_plan_overdue';
    case ScheduleUpcoming = 'schedule_upcoming';
    case StockLow = 'stock_low';
    case WorkOrderOverdue = 'work_order_overdue';
    case MtbfBelowThreshold = 'mtbf_below_threshold';

    public function label(): string
    {
        return match ($this) {
            self::MaintenancePlanOverdue => 'Plan preventivo vencido',
            self::ScheduleUpcoming => 'Mantenimiento próximo a vencer',
            self::StockLow => 'Stock bajo punto de reorden',
            self::WorkOrderOverdue => 'Orden de trabajo vencida',
            self::MtbfBelowThreshold => 'MTBF crítico',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MaintenancePlanOverdue => 'Actúa cuando un plan de mantenimiento preventivo supera su fecha programada.',
            self::ScheduleUpcoming => 'Notifica cuando un mantenimiento está próximo a vencer (configurable en días).',
            self::StockLow => 'Actúa cuando el stock de un repuesto cae por debajo del punto de reorden.',
            self::WorkOrderOverdue => 'Actúa cuando una OT supera su fecha planificada de cierre.',
            self::MtbfBelowThreshold => 'Actúa cuando el MTBF de un equipo cae por debajo del umbral configurado.',
        };
    }

    /** Default configuration for new rules of this type. */
    public function defaultConfiguration(): array
    {
        return match ($this) {
            self::ScheduleUpcoming => ['days_ahead' => 7],
            self::MtbfBelowThreshold => ['threshold_hours' => 500],
            default => [],
        };
    }
}
