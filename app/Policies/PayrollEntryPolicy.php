<?php

namespace App\Policies;

use App\Models\PayrollEntry;
use App\Models\User;

/**
 * El renglón no se escribe a mano.
 *
 * Se deriva de las horas confirmadas, las novedades, las bonificaciones y los parámetros
 * vigentes. Editarlo dejaría una cifra que no se puede explicar desde ninguna de esas
 * cuatro fuentes, que es exactamente lo que pasa hoy con las bonificaciones pegadas en el
 * Excel. Se corrige arreglando el dato de origen y volviendo a liquidar.
 */
class PayrollEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('payroll-runs.view');
    }

    public function view(User $user, PayrollEntry $entry): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('payroll-runs.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PayrollEntry $entry): bool
    {
        return false;
    }

    public function delete(User $user, PayrollEntry $entry): bool
    {
        return false;
    }

    /** Imprimir el desprendible. Solo quien puede ver sueldos. */
    public function print(User $user, PayrollEntry $entry): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-salaries.view');
    }
}
