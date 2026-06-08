<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

class AreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('areas.view');
    }

    public function view(User $user, Area $area): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('areas.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('areas.create');
    }

    public function update(User $user, Area $area): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('areas.edit');
    }

    public function delete(User $user, Area $area): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('areas.delete');
    }

    public function restore(User $user, Area $area): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('areas.delete');
    }

    public function forceDelete(User $user, Area $area): bool
    {
        return $user->is_super_admin;
    }
}
