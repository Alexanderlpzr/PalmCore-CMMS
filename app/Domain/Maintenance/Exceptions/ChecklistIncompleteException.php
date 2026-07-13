<?php

namespace App\Domain\Maintenance\Exceptions;

use App\Exceptions\BusinessRuleException;
use App\Models\WorkOrder;
use App\Models\WorkOrderTask;

/**
 * Raised when work is declared finished but the checklist says otherwise.
 *
 * This is the rule that turns the preventive module from decorative into real:
 * an OT cannot be closed with unanswered required measurements.
 */
class ChecklistIncompleteException extends BusinessRuleException
{
    public static function forTask(WorkOrderTask $task, int $missing): self
    {
        return new self(
            sprintf(
                'No se puede completar la tarea «%s»: faltan %d medición(es) obligatoria(s).',
                $task->title,
                $missing,
            ),
            detail: "task:{$task->id}",
        );
    }

    public static function forWorkOrder(WorkOrder $workOrder, int $missing): self
    {
        return new self(
            sprintf(
                'No se puede completar la Orden de Trabajo %s: faltan %d medición(es) obligatoria(s) del checklist.',
                $workOrder->work_order_number,
                $missing,
            ),
            detail: "work_order:{$workOrder->id}",
        );
    }

    public static function forWorkOrderTasks(WorkOrder $workOrder, int $unresolved): self
    {
        return new self(
            sprintf(
                'No se puede completar la Orden de Trabajo %s: %d tarea(s) sin ejecutar ni omitir.',
                $workOrder->work_order_number,
                $unresolved,
            ),
            detail: "work_order:{$workOrder->id}",
        );
    }
}
