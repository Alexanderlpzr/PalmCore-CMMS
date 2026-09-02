<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/**
 * Ver al empleado y ver su sueldo son dos facultades distintas.
 *
 * Portería necesita saber a quién acaba de escanear y para eso le basta el nombre. Por
 * eso `viewSalary` es un permiso aparte y no una consecuencia de `employees.view`: es el
 * único dato del sistema que el administrador del tenant tampoco recibe por omisión.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employees.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employees.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employees.update');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employees.delete');
    }

    public function restore(User $user, Employee $employee): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employees.delete');
    }

    public function forceDelete(User $user, Employee $employee): bool
    {
        return $user->is_super_admin;
    }

    /** El sueldo de una persona concreta. Ver la nota de cabecera. */
    public function viewSalary(User $user, Employee $employee): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-salaries.view');
    }

    /**
     * Lo mismo, sin un empleado delante: es lo que preguntan la columna de la tabla y la
     * sección del formulario antes de que exista el registro.
     *
     * Hace falta como método aparte porque `Gate` descarta el nombre de clase que se le
     * pasa y llama al método solo con el usuario; `viewSalary`, que exige un `Employee`,
     * no se puede invocar así y Laravel entonces devuelve null —es decir, deniega— sin
     * lanzar ningún error. La columna del sueldo quedaría invisible incluso para talento
     * humano y nada lo delataría.
     */
    public function viewAnySalary(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-salaries.view');
    }

    public function manageQrCode(User $user, Employee $employee): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employee-qr.update');
    }
}
