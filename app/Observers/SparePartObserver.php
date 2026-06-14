<?php

namespace App\Observers;

use App\Domain\Alerts\Services\AlertService;
use App\Models\SparePart;

class SparePartObserver
{
    public function __construct(private readonly AlertService $alertService) {}

    public function deleting(SparePart $sparePart): void
    {
        $this->alertService->autoResolveForEntity(
            tenantId: $sparePart->tenant_id,
            entityType: 'spare_part',
            entityId: $sparePart->id,
            entityName: "{$sparePart->code} — {$sparePart->name}",
        );
    }
}
