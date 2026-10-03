<?php

namespace App\Http\Middleware;

use App\Domain\Shared\Enums\SubscriptionStatus;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var PersonalAccessToken|null $token */
        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || $token->tenant_id === null) {
            return response()->json(['message' => 'API token is not scoped to a tenant.'], 403);
        }

        $tenant = $token->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 403);
        }

        CurrentTenant::set($tenant);

        Log::withContext([
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()?->id,
        ]);

        if (app()->bound('sentry')) {
            \Sentry\configureScope(function (Scope $scope) use ($tenant, $request): void {
                $scope->setUser(['id' => $request->user()?->id]);
                $scope->setContext('tenant', [
                    'id' => $tenant->id,
                    'slug' => $tenant->slug ?? 'unknown',
                ]);
            });
        }

        // Make Spatie team-scoped permissions (and therefore policies) resolve
        // against the token's tenant during API requests.
        setPermissionsTeamId($tenant->id);

        // Lo mismo que en la web (CheckTenantSubscription): una empresa suspendida o
        // con el plan vencido consulta, pero no crea ni cambia nada. Atado al
        // contenedor, las políticas lo ven; el rechazo de abajo cubre además las
        // escrituras que no pasan por ninguna política, que en la API son la mayoría.
        $status = $tenant->effectiveSubscriptionStatus();
        app()->instance('subscription.status', $status);

        if ($this->isBlockedWrite($request, $status)) {
            return response()->json([
                'message' => $status->writeRejectionMessage(),
                'code' => 'tenant_read_only',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Toda escritura, menos dar de alta o de baja el aviso push del teléfono: no toca
     * los datos de la planta, y darlo de baja es parte de cerrar sesión.
     */
    private function isBlockedWrite(Request $request, SubscriptionStatus $status): bool
    {
        return ! $status->allowsMutations()
            && ! $request->isMethodSafe()
            && ! $request->is('api/v1/push-subscriptions');
    }
}
