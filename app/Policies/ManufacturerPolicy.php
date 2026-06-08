<?php

namespace App\Policies;

use App\Models\Manufacturer;
use App\Models\User;

class ManufacturerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('manufacturers.view');
    }

    public function view(User $user, Manufacturer $manufacturer): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('manufacturers.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('manufacturers.create');
    }

    public function update(User $user, Manufacturer $manufacturer): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('manufacturers.update');
    }

    public function delete(User $user, Manufacturer $manufacturer): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('manufacturers.delete');
    }

    public function restore(User $user, Manufacturer $manufacturer): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('manufacturers.delete');
    }

    public function forceDelete(User $user, Manufacturer $manufacturer): bool
    {
        return $user->is_super_admin;
    }
}
