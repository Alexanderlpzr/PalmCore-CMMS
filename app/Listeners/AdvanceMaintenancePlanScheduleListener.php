<?php

namespace App\Listeners;

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\PreventiveWorkOrderGenerator;
use App\Events\WorkOrderStatusChanged;

/**
 * A preventive that was executed must push its plan forward.
 *
 * Listening to the event (instead of calling the generator from WorkOrderService)
 * keeps the dependency pointing one way: the generator knows about work orders,
 * work orders know nothing about the generator.
 */
class AdvanceMaintenancePlanScheduleListener
{
    public function __construct(
        private readonly PreventiveWorkOrderGenerator $generator,
    ) {}

    public function handle(WorkOrderStatusChanged $event): void
    {
        // Completada (flujo heredado) o Cerrada directamente (flujo vigente
        // Abierta → Cerrada): ambas cuentan como ejecución del preventivo.
        // recordCompletion es idempotente, así que pasar por las dos no adelanta
        // el plan dos veces.
        if (! in_array($event->toStatus, [WorkOrderStatus::Completed, WorkOrderStatus::Closed], strict: true)) {
            return;
        }

        $this->generator->recordCompletion($event->workOrder);
    }
}
