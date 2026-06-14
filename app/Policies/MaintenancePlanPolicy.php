<?php

namespace App\Policies;

use App\Models\MaintenancePlan;
use App\Models\User;

class MaintenancePlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-plans.view');
    }

    public function view(User $user, MaintenancePlan $maintenancePlan): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-plans.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-plans.create');
    }

    public function update(User $user, MaintenancePlan $maintenancePlan): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-plans.update');
    }

    public function delete(User $user, MaintenancePlan $maintenancePlan): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-plans.delete');
    }

    public function restore(User $user, MaintenancePlan $maintenancePlan): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-plans.delete');
    }

    public function forceDelete(User $user, MaintenancePlan $maintenancePlan): bool
    {
        return $user->is_super_admin;
    }

    public function activate(User $user, MaintenancePlan $maintenancePlan): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-plans.activate');
    }

    public function manageTasks(User $user, MaintenancePlan $maintenancePlan): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-plan-tasks.create');
    }

    public function manageAttachments(User $user, MaintenancePlan $maintenancePlan): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-plan-attachments.create');
    }

    public function recordMeterReading(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('equipment-meter-readings.create');
    }
}
