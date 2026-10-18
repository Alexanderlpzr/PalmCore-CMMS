<?php

namespace App\Policies;

use App\Models\User;

/**
 * Los conteos son ajustes de inventario: verlos pide inventory.view; abrirlos, contar,
 * aplicarlos o cancelarlos, inventory.adjust.
 */
class InventoryCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('inventory.view');
    }

    public function view(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('inventory.adjust');
    }

    public function update(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('inventory.adjust');
    }

    public function delete(User $user): bool
    {
        return false;
    }
}
