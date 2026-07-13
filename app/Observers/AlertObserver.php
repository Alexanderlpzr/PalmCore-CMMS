<?php

namespace App\Observers;

use App\Models\Alert;
use Illuminate\Support\Facades\Cache;

class AlertObserver
{
    public function created(Alert $alert): void
    {
        Cache::forget("home:{$alert->tenant_id}:attention");
    }

    public function updated(Alert $alert): void
    {
        if ($alert->wasChanged(['status', 'severity'])) {
            Cache::forget("home:{$alert->tenant_id}:attention");
        }
    }

    public function deleted(Alert $alert): void
    {
        Cache::forget("home:{$alert->tenant_id}:attention");
    }
}
