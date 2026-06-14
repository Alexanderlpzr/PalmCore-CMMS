<?php

namespace App\Policies;

use App\Models\SparePart;
use App\Models\User;

class SparePartPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('spare-parts.view');
    }

    public function view(User $user, SparePart $sparePart): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('spare-parts.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('spare-parts.create');
    }

    public function update(User $user, SparePart $sparePart): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('spare-parts.update');
    }

    public function delete(User $user, SparePart $sparePart): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('spare-parts.delete');
    }

    public function restore(User $user, SparePart $sparePart): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('spare-parts.delete');
    }

    public function forceDelete(User $user, SparePart $sparePart): bool
    {
        return $user->is_super_admin;
    }
}
