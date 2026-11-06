<?php

namespace App\Policies;

use App\Models\SstFinding;
use App\Models\User;

/**
 * Los hallazgos de SST: verlos pide sst-findings.view; reportarlos, corregir sus datos y
 * verificarlos, sst-findings.manage (SST y la administración). Ejecutar la parte de un
 * área se decide en SafetyFindingService::canActOn().
 */
class SstFindingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('sst-findings.view');
    }

    public function view(User $user, SstFinding $finding): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->hasPermissionTo('sst-findings.manage');
    }

    public function update(User $user, SstFinding $finding): bool
    {
        return $this->create($user);
    }

    public function verify(User $user, SstFinding $finding): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, SstFinding $finding): bool
    {
        return $this->create($user);
    }

    public function restore(User $user, SstFinding $finding): bool
    {
        return $this->create($user);
    }

    public function forceDelete(User $user, SstFinding $finding): bool
    {
        return false;
    }
}
