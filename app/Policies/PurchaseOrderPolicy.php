<?php

namespace App\Policies;

use App\Models\User;

/**
 * Purchase orders are governed by the inventory permissions: viewing needs
 * inventory.view, creating/receiving/cancelling needs inventory.entry (a PO is a
 * stock entry into a warehouse).
 */
class PurchaseOrderPolicy
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
        return $user->is_super_admin || $user->hasPermissionTo('inventory.entry');
    }

    public function update(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('inventory.entry');
    }

    public function delete(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('inventory.entry');
    }
}
