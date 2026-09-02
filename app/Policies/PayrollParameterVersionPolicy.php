<?php

namespace App\Policies;

use App\Models\PayrollParameterVersion;
use App\Models\User;

/**
 * Un tramo de vigencia cerrado es historia y no se toca.
 *
 * Editarlo cambiaría el resultado de nóminas ya liquidadas, aportadas y pagadas. Solo se
 * puede corregir el tramo abierto, que es el que todavía no ha liquidado nada.
 */
class PayrollParameterVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('payroll-parameters.view');
    }

    public function view(User $user, PayrollParameterVersion $version): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('payroll-parameters.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('payroll-parameters.manage');
    }

    public function update(User $user, PayrollParameterVersion $version): bool
    {
        if ($version->effective_to !== null) {
            return false;
        }

        return $user->is_super_admin || $user->hasPermissionTo('payroll-parameters.manage');
    }

    public function delete(User $user, PayrollParameterVersion $version): bool
    {
        if ($version->effective_to !== null) {
            return false;
        }

        return $user->is_super_admin || $user->hasPermissionTo('payroll-parameters.manage');
    }
}
