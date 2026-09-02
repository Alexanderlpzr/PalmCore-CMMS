<?php

namespace App\Policies;

use App\Models\EmployeeBonus;
use App\Models\User;

/**
 * Se gobierna con el mismo permiso que las novedades: son los valores que talento humano
 * carga cada mes por trabajador, y separarlos en facultades distintas sería multiplicar
 * permisos sin que nadie los use por separado.
 */
class EmployeeBonusPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.view');
    }

    public function view(User $user, EmployeeBonus $record): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.manage');
    }

    public function update(User $user, EmployeeBonus $record): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.manage');
    }

    public function delete(User $user, EmployeeBonus $record): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-novelties.manage');
    }

    public function forceDelete(User $user, EmployeeBonus $record): bool
    {
        return $user->is_super_admin;
    }
}
