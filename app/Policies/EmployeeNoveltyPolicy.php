<?php

namespace App\Policies;

use App\Models\EmployeeNovelty;
use App\Models\User;

class EmployeeNoveltyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.view');
    }

    public function view(User $user, EmployeeNovelty $novelty): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.manage');
    }

    public function update(User $user, EmployeeNovelty $novelty): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.manage');
    }

    public function delete(User $user, EmployeeNovelty $novelty): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.manage');
    }

    public function forceDelete(User $user, EmployeeNovelty $novelty): bool
    {
        return $user->is_super_admin;
    }
}
