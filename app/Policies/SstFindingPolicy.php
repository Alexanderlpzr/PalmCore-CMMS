<?php

namespace App\Policies;

use App\Models\SstFinding;
use App\Models\User;

/**
 * Los hallazgos de SST: verlos pide sst-findings.view; reportarlos y verificarlos,
 * sst-findings.manage (SST, Ambiental y la administración). Corregirlos o borrarlos, solo
 * la cuenta de su origen (2026-11-20). Ejecutar la parte de un
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

    /**
     * Corregir un hallazgo: solo la cuenta de su origen —SST los de SST, Ambiental los
     * ambientales—, que es quien lo reportó (2026-11-20). Ni la otra cuenta ni la
     * administración; Fronda (superadministrador) sí, para soporte.
     */
    public function update(User $user, SstFinding $finding): bool
    {
        return $this->isOwner($user, $finding);
    }

    public function verify(User $user, SstFinding $finding): bool
    {
        return $this->create($user);
    }

    /** Borrar uno mal hecho: lo mismo que corregirlo, solo la cuenta de su origen. */
    public function delete(User $user, SstFinding $finding): bool
    {
        return $this->isOwner($user, $finding);
    }

    public function restore(User $user, SstFinding $finding): bool
    {
        return $this->isOwner($user, $finding);
    }

    public function forceDelete(User $user, SstFinding $finding): bool
    {
        return false;
    }

    private function isOwner(User $user, SstFinding $finding): bool
    {
        return $user->is_super_admin
            || ($user->hasPermissionTo('sst-findings.manage') && $user->hasRole($finding->origin->role()));
    }
}
