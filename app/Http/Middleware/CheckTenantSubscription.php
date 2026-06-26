<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant === null) {
            return $next($request);
        }

        $effectiveStatus = $tenant->effectiveSubscriptionStatus();

        // Expose status to Gate::before for mutation enforcement
        app()->instance('subscription.status', $effectiveStatus);

        // Show a one-per-session notification when subscription expires soon
        // (healthy active/trial tenants only — degraded states use the persistent banner)
        if ($effectiveStatus->allowsMutations() && $tenant->isExpiringSoon()) {
            $sessionKey = "sub_warning_{$tenant->id}";

            if (! session()->has($sessionKey)) {
                $days = $tenant->daysUntilExpiry();
                $expiresAt = $tenant->subscription_expires_at;

                Notification::make()
                    ->title('Suscripción por vencer')
                    ->body("Tu plan vence en {$days} día(s), el {$expiresAt->format('d/m/Y')}. Renueva pronto para no perder el acceso.")
                    ->warning()
                    ->persistent()
                    ->send();

                session()->put($sessionKey, true);
            }
        }

        return $next($request);
    }
}
