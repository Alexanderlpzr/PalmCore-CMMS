<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('tenants.view');
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('tenants.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('tenants.create');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('tenants.update');
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('tenants.delete');
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return $user->is_super_admin;
    }

    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return $user->is_super_admin;
    }
}
