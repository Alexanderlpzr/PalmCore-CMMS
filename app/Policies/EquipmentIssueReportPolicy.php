<?php

namespace App\Policies;

use App\Models\EquipmentIssueReport;
use App\Models\User;

class EquipmentIssueReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('issue-reports.view');
    }

    public function view(User $user, EquipmentIssueReport $equipmentIssueReport): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('issue-reports.view');
    }

    public function create(User $user): bool
    {
        return false; // Created only via public QR form
    }

    public function update(User $user, EquipmentIssueReport $equipmentIssueReport): bool
    {
        return false; // Mutated only via acknowledge / convert actions
    }

    public function delete(User $user, EquipmentIssueReport $equipmentIssueReport): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('issue-reports.archive');
    }

    public function restore(User $user, EquipmentIssueReport $equipmentIssueReport): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('issue-reports.archive');
    }

    public function forceDelete(User $user, EquipmentIssueReport $equipmentIssueReport): bool
    {
        return $user->is_super_admin;
    }

    public function acknowledge(User $user, EquipmentIssueReport $equipmentIssueReport): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('issue-reports.acknowledge');
    }

    public function convert(User $user, EquipmentIssueReport $equipmentIssueReport): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('maintenance-requests.create');
    }
}
