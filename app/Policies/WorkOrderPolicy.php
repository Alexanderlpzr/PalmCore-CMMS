<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('work-orders.view');
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('work-orders.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('work-orders.create');
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        if (! $workOrder->isEditable()) {
            return false;
        }

        return $user->is_super_admin || $user->hasPermissionTo('work-orders.update');
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('work-orders.delete');
    }

    public function restore(User $user, WorkOrder $workOrder): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('work-orders.delete');
    }

    public function forceDelete(User $user, WorkOrder $workOrder): bool
    {
        return $user->is_super_admin;
    }

    public function plan(User $user, WorkOrder $workOrder): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('work-orders.plan');
    }

    public function execute(User $user, WorkOrder $workOrder): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('work-orders.execute');
    }

    public function verify(User $user, WorkOrder $workOrder): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('work-orders.verify');
    }

    public function close(User $user, WorkOrder $workOrder): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('work-orders.close');
    }
}
