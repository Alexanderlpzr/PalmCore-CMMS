<?php

namespace App\Policies;

use App\Models\EquipmentWorkedHours;
use App\Models\User;

/**
 * Un registro de horas trabajadas no se edita ni se borra: es lo que se anotó ese
 * día. Si estaba mal, se registra un nuevo renglón — igual que una lectura de
 * horómetro. Reutiliza los permisos de ese mismo dominio (equipment-meter-readings)
 * en vez de crear abilities nuevas por acción.
 */
class EquipmentWorkedHoursPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-meter-readings.view');
    }

    public function view(User $user, EquipmentWorkedHours $workedHours): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-meter-readings.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-meter-readings.create');
    }

    public function update(User $user, EquipmentWorkedHours $workedHours): bool
    {
        return false;
    }

    public function delete(User $user, EquipmentWorkedHours $workedHours): bool
    {
        return false;
    }
}
