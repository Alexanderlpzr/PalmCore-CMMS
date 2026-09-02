<?php

namespace App\Policies;

use App\Models\Holiday;
use App\Models\User;

class HolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('holidays.view');
    }

    public function view(User $user, Holiday $holiday): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('holidays.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('holidays.manage');
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('holidays.manage');
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('holidays.manage');
    }
}
