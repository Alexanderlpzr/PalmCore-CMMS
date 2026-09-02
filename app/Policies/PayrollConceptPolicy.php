<?php

namespace App\Policies;

use App\Models\PayrollConcept;
use App\Models\User;

class PayrollConceptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('payroll-concepts.view');
    }

    public function view(User $user, PayrollConcept $concept): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('payroll-concepts.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('payroll-concepts.manage');
    }

    public function update(User $user, PayrollConcept $concept): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('payroll-concepts.manage');
    }

    public function delete(User $user, PayrollConcept $concept): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('payroll-concepts.manage');
    }

    public function forceDelete(User $user, PayrollConcept $concept): bool
    {
        return $user->is_super_admin;
    }
}
