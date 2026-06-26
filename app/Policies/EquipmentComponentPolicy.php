<?php

namespace App\Policies;

use App\Models\EquipmentComponent;
use App\Models\User;

class EquipmentComponentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.view');
    }

    public function view(User $user, EquipmentComponent $component): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.create');
    }

    public function update(User $user, EquipmentComponent $component): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.update');
    }

    public function delete(User $user, EquipmentComponent $component): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.delete');
    }

    public function restore(User $user, EquipmentComponent $component): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.delete');
    }

    public function forceDelete(User $user, EquipmentComponent $component): bool
    {
        return $user->is_super_admin;
    }
}
