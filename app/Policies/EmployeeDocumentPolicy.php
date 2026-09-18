<?php

namespace App\Policies;

use App\Models\EmployeeDocument;
use App\Models\User;

/**
 * La carpeta del trabajador la lleva quien puede editar su ficha: ver
 * {@see EmployeePolicy::viewDocuments()} para por qué no basta con `employees.view`.
 */
class EmployeeDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->managesFolders($user);
    }

    public function view(User $user, EmployeeDocument $record): bool
    {
        return $this->managesFolders($user);
    }

    public function create(User $user): bool
    {
        return $this->managesFolders($user);
    }

    public function update(User $user, EmployeeDocument $record): bool
    {
        return $this->managesFolders($user);
    }

    public function delete(User $user, EmployeeDocument $record): bool
    {
        return $this->managesFolders($user);
    }

    public function forceDelete(User $user, EmployeeDocument $record): bool
    {
        return $user->is_super_admin;
    }

    private function managesFolders(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('employees.update');
    }
}
