<?php

namespace App\Policies;

use App\Models\EquipmentCategory;
use App\Models\User;

class EquipmentCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-categories.view');
    }

    public function view(User $user, EquipmentCategory $equipmentCategory): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-categories.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-categories.create');
    }

    public function update(User $user, EquipmentCategory $equipmentCategory): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-categories.update');
    }

    public function delete(User $user, EquipmentCategory $equipmentCategory): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-categories.delete');
    }

    public function restore(User $user, EquipmentCategory $equipmentCategory): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-categories.delete');
    }

    public function forceDelete(User $user, EquipmentCategory $equipmentCategory): bool
    {
        return $user->is_super_admin;
    }
}
