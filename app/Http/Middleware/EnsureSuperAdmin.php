<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to platform Super Admins. Used by cross-tenant endpoints
 * (e.g. the platform dashboard) that intentionally run without the api.tenant
 * scope, so authorization here is by user identity, never by tenant role.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_super_admin || ! $user->is_active) {
            abort(403, 'Esta sección es exclusiva para Super Admin.');
        }

        return $next($request);
    }
}
