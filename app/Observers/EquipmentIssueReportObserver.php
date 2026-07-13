<?php

namespace App\Observers;

use App\Models\EquipmentIssueReport;
use Illuminate\Support\Facades\Cache;

class EquipmentIssueReportObserver
{
    public function created(EquipmentIssueReport $equipmentIssueReport): void
    {
        Cache::forget("home:{$equipmentIssueReport->tenant_id}:attention");
    }

    public function updated(EquipmentIssueReport $equipmentIssueReport): void
    {
        if ($equipmentIssueReport->wasChanged('status')) {
            Cache::forget("home:{$equipmentIssueReport->tenant_id}:attention");
        }
    }

    public function deleted(EquipmentIssueReport $equipmentIssueReport): void
    {
        Cache::forget("home:{$equipmentIssueReport->tenant_id}:attention");
    }
}
