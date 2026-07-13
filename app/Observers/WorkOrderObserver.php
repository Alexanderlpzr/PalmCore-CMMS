<?php

namespace App\Observers;

use App\Domain\Alerts\Services\AlertService;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Reliability\Services\EquipmentKpiService;
use App\Jobs\RecalculateEquipmentKpisJob;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Sentry\State\Scope;

class WorkOrderObserver
{
    public function __construct(
        private readonly EquipmentKpiService $service,
        private readonly AlertService $alertService,
    ) {}

    public function created(WorkOrder $workOrder): void
    {
        Cache::forget("home:{$workOrder->tenant_id}:attention");
    }

    public function updated(WorkOrder $workOrder): void
    {
        if ($workOrder->wasChanged(['status', 'planned_end_at'])) {
            Cache::forget("home:{$workOrder->tenant_id}:attention");
        }

        if ($workOrder->equipment_id === null) {
            return;
        }

        if (! $workOrder->wasChanged('status')) {
            return;
        }

        if (! in_array($workOrder->status, [WorkOrderStatus::Completed, WorkOrderStatus::Closed], strict: true)) {
            return;
        }

        Log::withContext([
            'work_order_id' => $workOrder->id,
            'work_order_number' => $workOrder->work_order_number,
            'equipment_id' => $workOrder->equipment_id,
            'tenant_id' => $workOrder->tenant_id,
            'status' => $workOrder->status->value,
        ]);
        Log::info('work_order.status_changed');

        if (app()->bound('sentry')) {
            \Sentry\configureScope(function (Scope $scope) use ($workOrder): void {
                $scope->setContext('work_order', [
                    'id' => $workOrder->id,
                    'number' => $workOrder->work_order_number,
                    'equipment_id' => $workOrder->equipment_id,
                    'status' => $workOrder->status->value,
                ]);
            });
        }

        $this->service->markStale($workOrder->equipment_id);

        RecalculateEquipmentKpisJob::dispatch($workOrder->equipment_id)->afterCommit();
    }

    public function deleting(WorkOrder $workOrder): void
    {
        Cache::forget("home:{$workOrder->tenant_id}:attention");

        $this->alertService->autoResolveForEntity(
            tenantId: $workOrder->tenant_id,
            entityType: 'work_order',
            entityId: $workOrder->id,
            entityName: $workOrder->work_order_number,
        );
    }
}
