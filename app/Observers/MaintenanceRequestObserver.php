<?php

namespace App\Observers;

use App\Models\MaintenanceRequest;
use Illuminate\Support\Facades\Cache;

class MaintenanceRequestObserver
{
    public function created(MaintenanceRequest $maintenanceRequest): void
    {
        Cache::forget("home:{$maintenanceRequest->tenant_id}:attention");
    }

    public function updated(MaintenanceRequest $maintenanceRequest): void
    {
        if ($maintenanceRequest->wasChanged('status')) {
            Cache::forget("home:{$maintenanceRequest->tenant_id}:attention");
        }
    }

    public function deleted(MaintenanceRequest $maintenanceRequest): void
    {
        Cache::forget("home:{$maintenanceRequest->tenant_id}:attention");
    }
}
