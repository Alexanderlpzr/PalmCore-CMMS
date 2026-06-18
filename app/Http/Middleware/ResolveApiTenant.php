<?php

namespace App\Http\Middleware;

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

        return $next($request);
    }
}
