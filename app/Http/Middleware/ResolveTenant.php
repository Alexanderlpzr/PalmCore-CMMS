<?php

namespace App\Http\Middleware;

use App\Domain\Shared\Exceptions\TenantNotFoundException;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Infrastructure\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private readonly TenantResolver $resolver) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Tenant resolution happens at the route group level for tenant-scoped routes.
        // Routes outside this middleware (e.g., landing page, login) skip tenant resolution.
        try {
            $tenantSlug = $this->resolver->resolve($request);

            $tenant = \DB::table('tenants')
                ->where('slug', $tenantSlug)
                ->where('is_active', true)
                ->first();

            if (! $tenant) {
                abort(404, 'Tenant not found.');
            }

            CurrentTenant::set($tenant);

            Log::withContext([
                'tenant_id' => $tenant->id,
                'user_id' => $request->user()?->id,
            ]);

            // Spatie Permission Teams — must be called before any hasRole() / hasPermissionTo() check.
            // Setting the team context here ensures all permission checks in this request
            // are scoped to the correct tenant, regardless of where they are evaluated.
            setPermissionsTeamId($tenant->id);
        } catch (TenantNotFoundException) {
            abort(404, 'Tenant not found.');
        }

        $response = $next($request);

        CurrentTenant::clear();

        return $response;
    }
}
