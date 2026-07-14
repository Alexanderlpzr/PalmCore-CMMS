<?php

namespace App\Policies;

use App\Models\EquipmentMeterReading;
use App\Models\User;

/**
 * Una lectura de horómetro no se edita ni se borra: es lo que el dial decía ese día.
 *
 * Si estaba mal, se registra la lectura correcta y el acumulado se corrige solo —
 * incluido el caso del dial cambiado, que el servicio trata como reset y no como
 * error. Editar el pasado aquí rompería el acumulado de toda la máquina.
 */
class EquipmentMeterReadingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-meter-readings.view');
    }

    public function view(User $user, EquipmentMeterReading $reading): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-meter-readings.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-meter-readings.create');
    }

    public function update(User $user, EquipmentMeterReading $reading): bool
    {
        return false;
    }

    public function delete(User $user, EquipmentMeterReading $reading): bool
    {
        return false;
    }
}
