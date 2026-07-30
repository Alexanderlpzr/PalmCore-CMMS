<?php

namespace App\Policies;

use App\Models\EquipmentSparePart;
use App\Models\User;

class EquipmentSparePartPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.view');
    }

    public function view(User $user, EquipmentSparePart $sparePart): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.create');
    }

    public function update(User $user, EquipmentSparePart $sparePart): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.update');
    }

    public function delete(User $user, EquipmentSparePart $sparePart): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.delete');
    }

    public function restore(User $user, EquipmentSparePart $sparePart): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment.delete');
    }

    public function forceDelete(User $user, EquipmentSparePart $sparePart): bool
    {
        return $user->is_super_admin;
    }
}
