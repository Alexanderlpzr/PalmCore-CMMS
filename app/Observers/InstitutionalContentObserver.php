<?php

namespace App\Observers;

use App\Models\InstitutionalContent;
use Illuminate\Support\Facades\Cache;

class InstitutionalContentObserver
{
    public function saved(InstitutionalContent $institutionalContent): void
    {
        Cache::tags(['institutional-content'])->flush();
    }

    public function deleted(InstitutionalContent $institutionalContent): void
    {
        Cache::tags(['institutional-content'])->flush();
    }

    public function restored(InstitutionalContent $institutionalContent): void
    {
        Cache::tags(['institutional-content'])->flush();
    }
}
