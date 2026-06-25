<?php

namespace App\Policies;

use App\Models\User;
use App\Services\SuperAdminGuard;

class UserPolicy
{
    public function __construct(private readonly SuperAdminGuard $guard) {}

    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('users.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('users.update');
    }

    public function delete(User $user, User $model): bool
    {
        // Defense for non-super-admin actors. Note: a super admin acting here is
        // short-circuited by Gate::before, so the authoritative guarantee lives
        // in UserObserver (model layer). See SAFE-1.
        if ($this->guard->isLastActiveSuperAdmin($model)) {
            return false;
        }

        return $user->is_super_admin || $user->hasPermissionTo('users.delete');
    }

    public function restore(User $user, User $model): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('users.delete');
    }

    public function forceDelete(User $user, User $model): bool
    {
        if ($this->guard->isLastActiveSuperAdmin($model)) {
            return false;
        }

        return $user->is_super_admin;
    }
}
