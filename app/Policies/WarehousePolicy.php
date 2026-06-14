<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('warehouses.view');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('warehouses.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('warehouses.create');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('warehouses.update');
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('warehouses.delete');
    }

    public function restore(User $user, Warehouse $warehouse): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('warehouses.delete');
    }

    public function forceDelete(User $user, Warehouse $warehouse): bool
    {
        return $user->is_super_admin;
    }
}
