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

        if ($tenant && $tenant->subscription_expires_at !== null) {
            $expiresAt = $tenant->subscription_expires_at;
            $now = now();

            if ($expiresAt->isPast()) {
                Notification::make()
                    ->title('Suscripción vencida')
                    ->body('El plan de tu empresa venció el '.$expiresAt->format('d/m/Y').'. Renueva tu suscripción para continuar usando el sistema.')
                    ->danger()
                    ->persistent()
                    ->send();
            } elseif ($now->diffInDays($expiresAt) <= 30) {
                $days = (int) $now->diffInDays($expiresAt);

                Notification::make()
                    ->title('Suscripción por vencer')
                    ->body("Tu plan vence en {$days} día(s), el {$expiresAt->format('d/m/Y')}. Renueva pronto para no perder el acceso.")
                    ->warning()
                    ->persistent()
                    ->send();
            }
        }

        return $next($request);
    }
}
