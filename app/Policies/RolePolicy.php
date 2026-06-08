<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('roles.edit');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('roles.delete');
    }

    public function restore(User $user, Role $role): bool
    {
        return $user->is_super_admin;
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return $user->is_super_admin;
    }
}
