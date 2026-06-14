<?php

namespace App\Observers;

use App\Domain\Assets\Services\ReferenceDataService;
use App\Models\Area;

class AreaObserver
{
    public function created(Area $area): void
    {
        ReferenceDataService::forgetAreas($area->plant_id, $area->tenant_id);
    }

    public function updated(Area $area): void
    {
        ReferenceDataService::forgetAreas($area->plant_id, $area->tenant_id);
    }

    public function deleted(Area $area): void
    {
        ReferenceDataService::forgetAreas($area->plant_id, $area->tenant_id);
    }

    public function restored(Area $area): void
    {
        ReferenceDataService::forgetAreas($area->plant_id, $area->tenant_id);
    }

    public function forceDeleted(Area $area): void
    {
        ReferenceDataService::forgetAreas($area->plant_id, $area->tenant_id);
    }
}
