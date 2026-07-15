<?php

namespace App\Observers;

use App\Domain\Alerts\Services\AlertService;
use App\Exceptions\BusinessRuleException;
use App\Models\EquipmentComponent;
use App\Models\MaintenancePlan;

class MaintenancePlanObserver
{
    public function __construct(private readonly AlertService $alertService) {}

    /**
     * Un plan de componente no puede apuntar a la pieza de otro equipo. `Create` pasa
     * por el servicio y `Edit` no —Filament edita el modelo directo—, así que la
     * regla vive aquí, en el único punto por el que las dos rutas pasan de verdad.
     *
     * @throws BusinessRuleException
     */
    public function saving(MaintenancePlan $plan): void
    {
        if ($plan->equipment_component_id === null) {
            return;
        }

        $component = EquipmentComponent::withoutGlobalScopes()
            ->where('tenant_id', $plan->tenant_id)
            ->find($plan->equipment_component_id);

        if ($component === null) {
            throw new BusinessRuleException('El componente indicado no existe en esta organización.');
        }

        if ($component->equipment_id !== $plan->equipment_id) {
            throw new BusinessRuleException(
                'Ese componente pertenece a otro equipo: un plan no puede mezclar el equipo y la pieza de instalaciones distintas.'
            );
        }
    }

    public function deleting(MaintenancePlan $plan): void
    {
        $this->alertService->autoResolveForEntity(
            tenantId: $plan->tenant_id,
            entityType: 'maintenance_plan',
            entityId: $plan->id,
            entityName: "{$plan->plan_number} — {$plan->name}",
        );
    }
}
