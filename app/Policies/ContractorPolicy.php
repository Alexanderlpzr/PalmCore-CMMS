<?php

namespace App\Policies;

use App\Models\Contractor;
use App\Models\User;

class ContractorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('contractors.view');
    }

    public function view(User $user, Contractor $contractor): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('contractors.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('contractors.create');
    }

    public function update(User $user, Contractor $contractor): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('contractors.update');
    }

    public function delete(User $user, Contractor $contractor): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('contractors.delete');
    }

    public function restore(User $user, Contractor $contractor): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('contractors.delete');
    }

    public function forceDelete(User $user, Contractor $contractor): bool
    {
        return $user->is_super_admin;
    }
}
