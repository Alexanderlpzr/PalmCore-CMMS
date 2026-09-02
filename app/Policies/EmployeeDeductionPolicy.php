<?php

namespace App\Policies;

use App\Models\EmployeeDeduction;
use App\Models\User;

/**
 * Mismo permiso que las novedades y las bonificaciones: los tres son los valores que
 * talento humano carga cada mes por trabajador.
 */
class EmployeeDeductionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.view');
    }

    public function view(User $user, EmployeeDeduction $record): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.manage');
    }

    public function update(User $user, EmployeeDeduction $record): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.manage');
    }

    public function delete(User $user, EmployeeDeduction $record): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.manage');
    }

    public function forceDelete(User $user, EmployeeDeduction $record): bool
    {
        return $user->is_super_admin;
    }
}
