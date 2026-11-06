<?php

namespace App\Domain\Notifications;

use App\Filament\Resources\Alerts\AlertResource;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\Inventory\SparePart\SparePartResource;
use App\Filament\Resources\Maintenance\MaintenancePlan\MaintenancePlanResource;
use App\Filament\Resources\Maintenance\MaintenanceRequest\MaintenanceRequestResource;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use Filament\Facades\Filament;
use Throwable;

/**
 * El enlace en el panel al registro del que habla un aviso viejo de la app móvil, en la
 * empresa en la que está la persona. Sin empresa a la vista, o si el registro ya no se
 * puede abrir, no hay enlace: el aviso se muestra igual.
 */
class PanelLinks
{
    /** @var array<string, class-string> */
    private const RESOURCES = [
        'work_order' => WorkOrderResource::class,
        'alert' => AlertResource::class,
        'maintenance_plan' => MaintenancePlanResource::class,
        'spare_part' => SparePartResource::class,
        'equipment' => EquipmentResource::class,
        'maintenance_request' => MaintenanceRequestResource::class,
    ];

    public function to(string $type, string $recordId): ?string
    {
        $resource = self::RESOURCES[$type] ?? null;
        $tenant = Filament::getTenant();

        if ($resource === null || $tenant === null) {
            return null;
        }

        try {
            return $resource::getUrl('view', ['record' => $recordId], panel: 'admin', tenant: $tenant);
        } catch (Throwable) {
            return null;
        }
    }
}
