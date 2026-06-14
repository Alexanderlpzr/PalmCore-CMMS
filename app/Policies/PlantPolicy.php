<?php

namespace App\Policies;

use App\Models\Plant;
use App\Models\User;

class PlantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('plants.view');
    }

    public function view(User $user, Plant $plant): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('plants.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('plants.create');
    }

    public function update(User $user, Plant $plant): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('plants.update');
    }

    public function delete(User $user, Plant $plant): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('plants.delete');
    }

    public function restore(User $user, Plant $plant): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('plants.delete');
    }

    public function forceDelete(User $user, Plant $plant): bool
    {
        return $user->is_super_admin;
    }
}
