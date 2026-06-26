<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null) {
            return $next($request);
        }

        $requiresForSuperAdmin = $user->is_super_admin;
        $requiresForOwners = (bool) config('palmcore.security.require_two_factor_for_owners', false);
        $isTenantOwner = $requiresForOwners && $this->isTenantOwner($user, $request);

        if (! $requiresForSuperAdmin && ! $isTenantOwner) {
            return $next($request);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return $next($request);
        }

        // Show a one-per-session notification prompting 2FA setup.
        // Hard blocking is deferred until a self-service 2FA setup page exists.
        $sessionKey = '2fa_warning_shown_'.$user->id;

        if (! session()->has($sessionKey)) {
            Notification::make()
                ->title('Autenticación de dos factores requerida')
                ->body('Tu cuenta requiere 2FA. Actívalo desde Perfil → Seguridad antes de que sea obligatorio.')
                ->warning()
                ->persistent()
                ->send();

            session()->put($sessionKey, true);
        }

        return $next($request);
    }

    private function isTenantOwner(mixed $user, Request $request): bool
    {
        return (bool) $user->tenants()
            ->wherePivot('is_owner', true)
            ->exists();
    }
}
