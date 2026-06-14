<?php

namespace App\Policies;

use App\Models\User;

class InventoryPolicy
{
    public function view(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('inventory.view');
    }

    public function entry(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('inventory.entry');
    }

    public function exit(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('inventory.exit');
    }

    public function adjust(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('inventory.adjust');
    }

    public function transfer(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('inventory.transfer');
    }
}
