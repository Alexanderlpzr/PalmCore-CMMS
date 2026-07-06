<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Pages;

use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWorkOrder extends CreateRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(WorkOrderService::class);

        // technician_ids is a form-only convenience field (not a WO column) that
        // lets the creator assign técnicos up front, so the OT can go straight to
        // "Planificada" without visiting the relation manager first.
        $technicianIds = array_filter((array) ($data['technician_ids'] ?? []));
        unset($data['technician_ids']);

        $workOrder = $service->create(
            array_merge($data, ['tenant_id' => Filament::getTenant()->id]),
            auth()->user()
        );

        foreach (User::whereIn('id', $technicianIds)->get() as $technician) {
            $service->assignTechnician($workOrder, $technician, TechnicianRole::Technician);
        }

        return $workOrder;
    }
}
