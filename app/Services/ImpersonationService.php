<?php

namespace App\Services;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Native (no external package) session-based impersonation for Super Admins.
 *
 * Security model:
 *  - Only an active Super Admin can start an impersonation.
 *  - A Super Admin can never impersonate another Super Admin (no lateral
 *    privilege move), themselves, or an inactive user.
 *  - Impersonation cannot be nested.
 *  - While impersonating, the acting user IS the target (a non-super-admin), so
 *    the Gate::before super-admin bypass does not apply — no privilege elevation.
 *  - The session id is regenerated on start and stop to prevent session fixation.
 *  - The impersonator identity lives only in the server-side session, so the
 *    impersonated user cannot tamper with who they restore to.
 *  - Sanctum/API token auth is untouched; this is purely the web session guard.
 */
class ImpersonationService
{
    public const SESSION_KEY = 'impersonation';

    public function start(User $impersonator, User $target, ?string $reason, Request $request): ImpersonationLog
    {
        $this->assertCanImpersonate($impersonator, $target, $request);

        $tenant = $target->tenants()->wherePivot('is_primary_tenant', true)->first()
            ?? $target->tenants()->first();

        $log = ImpersonationLog::create([
            'impersonator_id' => $impersonator->getKey(),
            'impersonated_user_id' => $target->getKey(),
            'tenant_id' => $tenant?->getKey(),
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'reason' => $reason,
        ]);

        $request->session()->put(self::SESSION_KEY, [
            'log_id' => $log->getKey(),
            'impersonator_id' => $impersonator->getKey(),
            'impersonated_user_id' => $target->getKey(),
            'impersonated_name' => $target->name,
            'tenant_id' => $tenant?->getKey(),
            'tenant_name' => $tenant?->name,
            'started_at' => $log->started_at->toIso8601String(),
        ]);

        Auth::login($target);
        $request->session()->regenerate();

        return $log;
    }

    /**
     * Exit impersonation: close the audit record, restore the original Super
     * Admin session, and rotate the session id.
     */
    public function stop(Request $request): void
    {
        $context = $request->session()->get(self::SESSION_KEY);

        if (! $context) {
            return;
        }

        $log = ImpersonationLog::find($context['log_id']);

        if ($log && $log->ended_at === null) {
            $endedAt = now();
            $log->update([
                'ended_at' => $endedAt,
                'duration_seconds' => (int) $log->started_at->diffInSeconds($endedAt),
            ]);
        }

        $request->session()->forget(self::SESSION_KEY);

        $original = User::find($context['impersonator_id']);

        if ($original !== null) {
            Auth::login($original);
        } else {
            // Original account vanished mid-session — fail closed by logging out.
            Auth::logout();
        }

        $request->session()->regenerate();
    }

    public function isImpersonating(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function context(): ?array
    {
        return session()->get(self::SESSION_KEY);
    }

    private function assertCanImpersonate(User $impersonator, User $target, Request $request): void
    {
        if (! $impersonator->is_super_admin || ! $impersonator->is_active) {
            throw new AuthorizationException('Solo un Super Admin activo puede impersonar usuarios.');
        }

        if ($impersonator->is($target)) {
            throw new AuthorizationException('No puedes impersonarte a ti mismo.');
        }

        if ($target->is_super_admin) {
            throw new AuthorizationException('No puedes impersonar a otro Super Admin.');
        }

        if (! $target->is_active) {
            throw new AuthorizationException('No puedes impersonar a un usuario inactivo.');
        }

        if ($request->session()->has(self::SESSION_KEY)) {
            throw new AuthorizationException('Ya existe una impersonación activa. Sal de ella antes de iniciar otra.');
        }
    }
}
