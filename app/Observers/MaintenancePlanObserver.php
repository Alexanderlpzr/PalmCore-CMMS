<?php

namespace App\Observers;

use App\Domain\Alerts\Services\AlertService;
use App\Models\MaintenancePlan;

class MaintenancePlanObserver
{
    public function __construct(private readonly AlertService $alertService) {}

    public function deleting(MaintenancePlan $plan): void
    {
        $this->alertService->autoResolveForEntity(
            tenantId: $plan->tenant_id,
            entityType: 'maintenance_plan',
            entityId: $plan->id,
            entityName: "{$plan->plan_number} — {$plan->name}",
        );
    }
}
