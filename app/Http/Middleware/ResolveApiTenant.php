<?php

namespace App\Http\Middleware;

use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
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

        return $next($request);
    }
}
