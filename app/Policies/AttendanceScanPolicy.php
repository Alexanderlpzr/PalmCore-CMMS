<?php

namespace App\Policies;

use App\Models\AttendanceScan;
use App\Models\User;

/**
 * Las marcas de portería no se editan ni se borran.
 *
 * Son la prueba de a qué hora entró alguien a la planta y de ahí sale lo que se le paga.
 * Una marca equivocada se corrige registrando otra, con su nota y su autor; por eso
 * `update` y `delete` niegan a todo rol del tenant, incluido talento humano.
 *
 * El superadministrador de plataforma sí pasa, porque `Gate::before` lo deja pasar por
 * encima de todas las policies del producto. Esa puerta es anterior a este módulo y no
 * se abre aquí: cerrarla solo para esta tabla daría una falsa sensación de inmutabilidad
 * —la fila se puede tocar por SQL de todos modos— y rompería el patrón que siguen las
 * otras 30 policies. Si algún día la asistencia necesita ser inviolable de verdad, eso se
 * hace con una restricción en la base, no con una policy.
 */
class AttendanceScanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('attendance.view');
    }

    public function view(User $user, AttendanceScan $scan): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('attendance.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('attendance.record');
    }

    public function update(User $user, AttendanceScan $scan): bool
    {
        return false;
    }

    public function delete(User $user, AttendanceScan $scan): bool
    {
        return false;
    }
}
