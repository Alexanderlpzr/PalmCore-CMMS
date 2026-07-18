<?php

namespace App\Policies;

use App\Models\MaintenanceBudget;
use App\Models\User;

/**
 * Fijar el presupuesto es decidir contra qué se mide el gasto de todo el mes.
 * Verlo es otra cosa —la gerencia lo consulta sin poder moverlo—.
 */
class MaintenanceBudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-budgets.view');
    }

    public function view(User $user, MaintenanceBudget $budget): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-budgets.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-budgets.manage');
    }

    public function update(User $user, MaintenanceBudget $budget): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-budgets.manage');
    }

    public function delete(User $user, MaintenanceBudget $budget): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-budgets.manage');
    }
}
