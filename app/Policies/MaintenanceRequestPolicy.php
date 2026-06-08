<?php

namespace App\Policies;

use App\Models\MaintenanceRequest;
use App\Models\User;

class MaintenanceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-requests.view');
    }

    public function view(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-requests.view');
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-requests.create');
    }

    public function update(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        if (! $maintenanceRequest->isEditable()) {
            return false;
        }

        return $user->is_super_admin || $user->hasPermissionTo('maintenance-requests.update');
    }

    public function delete(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-requests.delete');
    }

    public function restore(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-requests.delete');
    }

    public function forceDelete(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $user->is_super_admin;
    }

    public function approve(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-requests.approve');
    }

    public function review(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-requests.review');
    }

    public function convert(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-requests.convert');
    }
}
