<?php

namespace App\Filament\Resources\Maintenance\MaintenancePlan\Pages;

use App\Domain\Maintenance\Services\MaintenancePlanService;
use App\Filament\Resources\Maintenance\MaintenancePlan\MaintenancePlanResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMaintenancePlan extends CreateRecord
{
    protected static string $resource = MaintenancePlanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(MaintenancePlanService::class)->create(
            array_merge($data, ['tenant_id' => Filament::getTenant()->id]),
            auth()->user()
        );
    }
}
