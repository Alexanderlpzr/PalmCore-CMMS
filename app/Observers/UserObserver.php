<?php

namespace App\Observers;

use App\Models\User;
use App\Services\SuperAdminGuard;

/**
 * Enforces the "last active Super Admin" invariant at the model layer, which is
 * the only place that cannot be bypassed: it fires for every Eloquent write
 * regardless of the entry point (Filament, API, queue jobs, tinker) and is
 * unaffected by the Gate::before super-admin bypass that short-circuits policies.
 */
class UserObserver
{
    public function __construct(private readonly SuperAdminGuard $guard) {}

    /**
     * Block (soft or force) deletion of the last active Super Admin.
     */
    public function deleting(User $user): void
    {
        if ($this->wasActiveSuperAdmin($user)) {
            $this->guard->assertAnotherActiveSuperAdminExists($user, SuperAdminGuard::MESSAGE_DELETE);
        }
    }

    /**
     * Block deactivation (is_active → false) or demotion (is_super_admin → false)
     * of the last active Super Admin.
     */
    public function updating(User $user): void
    {
        if (! $this->wasActiveSuperAdmin($user)) {
            return;
        }

        if ($user->isDirty('is_active') && ! $user->is_active) {
            $this->guard->assertAnotherActiveSuperAdminExists($user, SuperAdminGuard::MESSAGE_DEACTIVATE);

            return;
        }

        if ($user->isDirty('is_super_admin') && ! $user->is_super_admin) {
            $this->guard->assertAnotherActiveSuperAdminExists($user, SuperAdminGuard::MESSAGE_DEMOTE);
        }
    }

    /**
     * Whether the user was an active Super Admin *before* the current change,
     * evaluated from the persisted (original) attributes.
     */
    private function wasActiveSuperAdmin(User $user): bool
    {
        return (bool) $user->getOriginal('is_super_admin')
            && (bool) $user->getOriginal('is_active')
            && $user->getOriginal('deleted_at') === null;
    }
}
