<?php

namespace App\Domain\Assets\Services;

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Models\Equipment;

/**
 * Mutations for equipment master data. Centralizes attribute changes so they go
 * through the model (firing the same observers/audit as a Filament edit) rather
 * than being written directly from a controller.
 */
class EquipmentService
{
    public function changeStatus(Equipment $equipment, EquipmentStatus $status): Equipment
    {
        $equipment->update(['status' => $status->value]);

        return $equipment->refresh();
    }

    public function changeCriticality(Equipment $equipment, EquipmentCriticality $criticality): Equipment
    {
        $equipment->update(['criticality' => $criticality->value]);

        return $equipment->refresh();
    }
}
