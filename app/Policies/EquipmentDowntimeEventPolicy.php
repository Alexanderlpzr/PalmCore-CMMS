<?php

namespace App\Policies;

use App\Models\EquipmentDowntimeEvent;
use App\Models\User;

/**
 * Un paro es un hecho histórico: se registra, se clasifica y se firma. No se borra.
 *
 * `confirm` es una facultad aparte y de producción: quien registra las horas no
 * puede ser el mismo que certifica que la planta las perdió.
 */
class EquipmentDowntimeEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('downtime-events.view');
    }

    public function view(User $user, EquipmentDowntimeEvent $event): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('downtime-events.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('downtime-events.create');
    }

    public function update(User $user, EquipmentDowntimeEvent $event): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('downtime-events.update');
    }

    /** Firmar o disputar las horas perdidas. Es de producción, no de mantenimiento. */
    public function confirm(User $user, EquipmentDowntimeEvent $event): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('downtime-events.confirm');
    }

    /**
     * Nadie. Un paro que desaparece se lleva consigo las horas que la planta perdió
     * y deja la eficiencia del mes sin forma de auditarla.
     */
    public function delete(User $user, EquipmentDowntimeEvent $event): bool
    {
        return false;
    }
}
