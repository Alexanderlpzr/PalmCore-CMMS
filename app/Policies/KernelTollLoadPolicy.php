<?php

namespace App\Policies;

use App\Models\User;

/**
 * Ver {@see CommercialPolicy}. La Dirección de Producción también lo ve (production.view),
 * sin registrar ni corregir nada.
 */
class KernelTollLoadPolicy extends CommercialPolicy
{
    public function viewAny(User $user): bool
    {
        return parent::viewAny($user) || $user->hasPermissionTo('production.view');
    }
}
