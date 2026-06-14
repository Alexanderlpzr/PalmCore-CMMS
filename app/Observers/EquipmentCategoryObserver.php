<?php

namespace App\Observers;

use App\Domain\Assets\Services\ReferenceDataService;
use App\Models\EquipmentCategory;

class EquipmentCategoryObserver
{
    public function created(EquipmentCategory $equipmentCategory): void
    {
        ReferenceDataService::forgetCategories($equipmentCategory->tenant_id);
    }

    public function updated(EquipmentCategory $equipmentCategory): void
    {
        ReferenceDataService::forgetCategories($equipmentCategory->tenant_id);
    }

    public function deleted(EquipmentCategory $equipmentCategory): void
    {
        ReferenceDataService::forgetCategories($equipmentCategory->tenant_id);
    }

    public function restored(EquipmentCategory $equipmentCategory): void
    {
        ReferenceDataService::forgetCategories($equipmentCategory->tenant_id);
    }

    public function forceDeleted(EquipmentCategory $equipmentCategory): void
    {
        ReferenceDataService::forgetCategories($equipmentCategory->tenant_id);
    }
}
