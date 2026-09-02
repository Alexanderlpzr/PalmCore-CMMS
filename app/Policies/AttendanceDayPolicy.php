<?php

namespace App\Policies;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Models\AttendanceDay;
use App\Models\User;

/**
 * Ver las horas y firmarlas son dos facultades distintas.
 *
 * `attendance.confirm` es la que carga la responsabilidad: quien la tiene está diciendo
 * que esas horas son las que se trabajaron y las que se van a pagar. Portería no la
 * recibe —marca el reloj, no responde por él— y el editar queda cerrado para todos,
 * porque una fila se corrige reconstruyéndola desde las marcas, nunca escribiendo horas
 * a mano que no correspondan a ningún escaneo.
 */
class AttendanceDayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('attendance.view');
    }

    public function view(User $user, AttendanceDay $day): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('attendance.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AttendanceDay $day): bool
    {
        return false;
    }

    public function delete(User $user, AttendanceDay $day): bool
    {
        return false;
    }

    /** Firmar las horas del día. Solo tiene sentido sobre una propuesta. */
    public function confirm(User $user, AttendanceDay $day): bool
    {
        if ($day->status === AttendanceDayStatus::Confirmada) {
            return false;
        }

        return $user->is_super_admin || $user->hasPermissionTo('attendance.confirm');
    }

    /** Devolver un día firmado a propuesta, para rehacerlo. */
    public function reopen(User $user, AttendanceDay $day): bool
    {
        if ($day->status !== AttendanceDayStatus::Confirmada) {
            return false;
        }

        return $user->is_super_admin || $user->hasPermissionTo('attendance.confirm');
    }

    /** Reconstruir un período desde las marcas del reloj. */
    public function build(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('attendance.confirm');
    }
}
