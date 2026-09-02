<?php

namespace App\Policies;

use App\Models\PayrollRun;
use App\Models\User;

/**
 * Liquidar y cerrar son facultades distintas.
 *
 * Liquidar se puede repetir sin consecuencias mientras la nómina esté en borrador. Cerrar
 * es el punto de no retorno práctico: a partir de ahí se emiten los desprendibles, se
 * pagan y se aportan. Que sean permisos separados permite que quien prepara la nómina no
 * sea necesariamente quien la aprueba, aunque hoy los dos los tenga talento humano.
 */
class PayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('payroll-runs.view');
    }

    public function view(User $user, PayrollRun $run): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('payroll-runs.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('payroll-runs.manage');
    }

    /** Solo el borrador se toca. */
    public function update(User $user, PayrollRun $run): bool
    {
        if (! $run->isEditable()) {
            return false;
        }

        return $user->is_super_admin || $user->hasPermissionTo('payroll-runs.manage');
    }

    public function delete(User $user, PayrollRun $run): bool
    {
        if (! $run->isEditable()) {
            return false;
        }

        return $user->is_super_admin || $user->hasPermissionTo('payroll-runs.manage');
    }

    public function forceDelete(User $user, PayrollRun $run): bool
    {
        return $user->is_super_admin;
    }

    /** Rehacer los renglones desde las horas confirmadas. */
    public function calculate(User $user, PayrollRun $run): bool
    {
        if (! $run->isEditable()) {
            return false;
        }

        return $user->is_super_admin || $user->hasPermissionTo('payroll-runs.manage');
    }

    public function close(User $user, PayrollRun $run): bool
    {
        if (! $run->isEditable() || $run->calculated_at === null) {
            return false;
        }

        return $user->is_super_admin || $user->hasPermissionTo('payroll-runs.close');
    }

    public function reopen(User $user, PayrollRun $run): bool
    {
        if ($run->isEditable()) {
            return false;
        }

        return $user->is_super_admin || $user->hasPermissionTo('payroll-runs.close');
    }
}
