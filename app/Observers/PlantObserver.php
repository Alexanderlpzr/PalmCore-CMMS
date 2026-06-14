<?php

namespace App\Observers;

use App\Domain\Assets\Services\ReferenceDataService;
use App\Models\Plant;

class PlantObserver
{
    public function created(Plant $plant): void
    {
        ReferenceDataService::forgetPlants($plant->tenant_id);
    }

    public function updated(Plant $plant): void
    {
        ReferenceDataService::forgetPlants($plant->tenant_id);
    }

    public function deleted(Plant $plant): void
    {
        ReferenceDataService::forgetPlants($plant->tenant_id);
    }

    public function restored(Plant $plant): void
    {
        ReferenceDataService::forgetPlants($plant->tenant_id);
    }

    public function forceDeleted(Plant $plant): void
    {
        ReferenceDataService::forgetPlants($plant->tenant_id);
    }
}
