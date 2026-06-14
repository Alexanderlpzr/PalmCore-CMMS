<?php

namespace App\Observers;

use App\Domain\Alerts\Services\AlertService;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Reliability\Services\EquipmentKpiService;
use App\Jobs\RecalculateEquipmentKpisJob;
use App\Models\WorkOrder;

class WorkOrderObserver
{
    public function __construct(
        private readonly EquipmentKpiService $service,
        private readonly AlertService $alertService,
    ) {}

    public function updated(WorkOrder $workOrder): void
    {
        if ($workOrder->equipment_id === null) {
            return;
        }

        if (! $workOrder->wasChanged('status')) {
            return;
        }

        if (! in_array($workOrder->status, [WorkOrderStatus::Completed, WorkOrderStatus::Closed], strict: true)) {
            return;
        }

        $this->service->markStale($workOrder->equipment_id);

        RecalculateEquipmentKpisJob::dispatch($workOrder->equipment_id)->afterCommit();
    }

    public function deleting(WorkOrder $workOrder): void
    {
        $this->alertService->autoResolveForEntity(
            tenantId: $workOrder->tenant_id,
            entityType: 'work_order',
            entityId: $workOrder->id,
            entityName: $workOrder->work_order_number,
        );
    }
}
