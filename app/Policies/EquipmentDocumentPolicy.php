<?php

namespace App\Policies;

use App\Models\EquipmentDocument;
use App\Models\User;

class EquipmentDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-documents.view');
    }

    public function view(User $user, EquipmentDocument $equipmentDocument): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-documents.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-documents.create');
    }

    public function update(User $user, EquipmentDocument $equipmentDocument): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-documents.update');
    }

    public function delete(User $user, EquipmentDocument $equipmentDocument): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-documents.delete');
    }

    public function restore(User $user, EquipmentDocument $equipmentDocument): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-documents.delete');
    }

    public function forceDelete(User $user, EquipmentDocument $equipmentDocument): bool
    {
        return $user->is_super_admin;
    }
}
