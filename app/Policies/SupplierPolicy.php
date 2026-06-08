<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('suppliers.update');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('suppliers.delete');
    }

    public function restore(User $user, Supplier $supplier): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('suppliers.delete');
    }

    public function forceDelete(User $user, Supplier $supplier): bool
    {
        return $user->is_super_admin;
    }
}
