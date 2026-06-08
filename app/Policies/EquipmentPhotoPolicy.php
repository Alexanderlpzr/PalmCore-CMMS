<?php

namespace App\Policies;

use App\Models\EquipmentPhoto;
use App\Models\User;

class EquipmentPhotoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-photos.view');
    }

    public function view(User $user, EquipmentPhoto $equipmentPhoto): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-photos.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-photos.create');
    }

    public function update(User $user, EquipmentPhoto $equipmentPhoto): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-photos.update');
    }

    public function delete(User $user, EquipmentPhoto $equipmentPhoto): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-photos.delete');
    }

    public function restore(User $user, EquipmentPhoto $equipmentPhoto): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-photos.delete');
    }

    public function forceDelete(User $user, EquipmentPhoto $equipmentPhoto): bool
    {
        return $user->is_super_admin;
    }
}
