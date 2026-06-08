<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;

class EquipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.view');
    }

    public function view(User $user, Equipment $equipment): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.create');
    }

    public function update(User $user, Equipment $equipment): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.update');
    }

    public function delete(User $user, Equipment $equipment): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.delete');
    }

    public function restore(User $user, Equipment $equipment): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.delete');
    }

    public function forceDelete(User $user, Equipment $equipment): bool
    {
        return $user->is_super_admin;
    }
}
