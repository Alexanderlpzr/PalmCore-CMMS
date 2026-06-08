<?php

namespace App\Filament\Resources\Maintenance\MaintenanceRequest\Pages;

use App\Domain\Maintenance\Services\MaintenanceRequestService;
use App\Filament\Resources\Maintenance\MaintenanceRequest\MaintenanceRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceRequest extends CreateRecord
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(MaintenanceRequestService::class);

        return $service->create(
            array_merge($data, ['tenant_id' => \Filament\Facades\Filament::getTenant()->id]),
            auth()->user()
        );
    }
}
