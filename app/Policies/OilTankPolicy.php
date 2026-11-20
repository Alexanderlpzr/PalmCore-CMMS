<?php

namespace App\Policies;

use App\Models\User;

/**
 * Los tanques de aceite: los ve y los mide quien lleva oil-tanks.record (producción y la
 * Directora Administrativa) o lo comercial; el catálogo de tanques lo administra la
 * Directora (commercial.manage). La Dirección de Producción los ve sin medir (production.view).
 * Ver {@see CommercialPolicy}.
 */
class OilTankPolicy extends CommercialPolicy
{
    public function viewAny(User $user): bool
    {
        return parent::viewAny($user) || $user->hasPermissionTo('oil-tanks.record') || $user->hasPermissionTo('production.view');
    }

    /** Medir los tanques: registrar el nivel y la acidez del día. */
    public function record(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('oil-tanks.record');
    }
}
