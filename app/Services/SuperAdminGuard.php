<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\User;

/**
 * Guards the platform-wide invariant: at least one active Super Admin must
 * always exist. An "active Super Admin" is a user with is_super_admin = true
 * and is_active = true that is not soft-deleted.
 *
 * Enforcement is centralised here so every layer (model observer, policy,
 * Filament resource) shares one definition and one set of messages.
 */
class SuperAdminGuard
{
    public const MESSAGE_DELETE = 'No puedes eliminar al último Super Admin activo de la plataforma. Crea o activa otro Super Admin antes de continuar.';

    public const MESSAGE_DEACTIVATE = 'No puedes desactivar al último Super Admin activo de la plataforma. Debe existir al menos un Super Admin activo.';

    public const MESSAGE_DEMOTE = 'No puedes quitar el rol de Super Admin al último Super Admin activo de la plataforma.';

    /**
     * Whether the given user currently is an active Super Admin.
     */
    public function isActiveSuperAdmin(User $user): bool
    {
        return (bool) $user->is_super_admin
            && (bool) $user->is_active
            && ! $user->trashed();
    }

    /**
     * Read-only check used by the UI/policy layers: is this user the only
     * active Super Admin left? Not race-safe by itself — use the assertion
     * below for write operations.
     */
    public function isLastActiveSuperAdmin(User $user): bool
    {
        if (! $this->isActiveSuperAdmin($user)) {
            return false;
        }

        return ! User::query()
            ->where('is_super_admin', true)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    /**
     * Race-safe enforcement: ensure at least one active Super Admin other than
     * $excluding exists, throwing otherwise.
     *
     * Locks the full set of active Super Admin rows (including $excluding) with
     * SELECT ... FOR UPDATE so concurrent delete/deactivate/demote operations
     * serialise on the same rows: the second transaction blocks until the first
     * commits, then re-evaluates against the committed state and is rejected if
     * it would drain the last one. Requires an enclosing DB transaction to hold
     * the lock (Filament wraps record mutations in one; programmatic callers
     * should too).
     */
    public function assertAnotherActiveSuperAdminExists(User $excluding, string $message): void
    {
        $remaining = User::query()
            ->where('is_super_admin', true)
            ->where('is_active', true)
            ->lockForUpdate()
            ->pluck($excluding->getKeyName())
            ->reject(fn ($id): bool => $id === $excluding->getKey())
            ->count();

        if ($remaining === 0) {
            throw new BusinessRuleException($message);
        }
    }
}
