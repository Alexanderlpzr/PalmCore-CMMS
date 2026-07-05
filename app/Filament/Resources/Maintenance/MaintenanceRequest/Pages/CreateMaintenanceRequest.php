<?php

namespace App\Filament\Resources\Maintenance\MaintenanceRequest\Pages;

use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Services\MaintenanceRequestService;
use App\Filament\Resources\Maintenance\MaintenanceRequest\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMaintenanceRequest extends CreateRecord
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(MaintenanceRequestService::class);

        $request = $service->create(
            array_merge($data, ['tenant_id' => Filament::getTenant()->id]),
            auth()->user()
        );

        // Skip the manual "enviar"/"tomar para revisión" clicks — a request the
        // requester just submitted has nothing left for them to decide, so it
        // goes straight to the reviewer's queue (unless it already starts there,
        // e.g. emergency type).
        /** @var MaintenanceRequest $request */
        if ($request->status === MaintenanceRequestStatus::Draft) {
            $service->transition($request, MaintenanceRequestStatus::Submitted, auth()->user());
            $service->transition($request, MaintenanceRequestStatus::UnderReview, auth()->user());
        }

        return $request;
    }
}
