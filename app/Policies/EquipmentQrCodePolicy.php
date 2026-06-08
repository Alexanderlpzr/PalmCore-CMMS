<?php

namespace App\Policies;

use App\Models\EquipmentQrCode;
use App\Models\User;

class EquipmentQrCodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-qr.view');
    }

    public function view(User $user, EquipmentQrCode $qrCode): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-qr.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-qr.create');
    }

    public function update(User $user, EquipmentQrCode $qrCode): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-qr.update');
    }

    public function delete(User $user, EquipmentQrCode $qrCode): bool
    {
        return $user->is_super_admin;
    }

    public function forceDelete(User $user, EquipmentQrCode $qrCode): bool
    {
        return $user->is_super_admin;
    }
}
