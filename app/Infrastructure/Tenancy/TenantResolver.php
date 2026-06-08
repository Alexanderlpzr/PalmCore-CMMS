<?php

namespace App\Infrastructure\Tenancy;

use App\Domain\Shared\Exceptions\TenantNotFoundException;
use Illuminate\Http\Request;

/**
 * Resolves the active tenant from the incoming HTTP request.
 * Strategy: subdomain extraction → header fallback → session (for queued jobs).
 */
class TenantResolver
{
    public function resolve(Request $request): string
    {
        if ($tenantId = $this->fromSubdomain($request)) {
            return $tenantId;
        }

        if ($tenantId = $request->header('X-Tenant-Id')) {
            return $tenantId;
        }

        throw new TenantNotFoundException('Unable to determine tenant from request.');
    }

    private function fromSubdomain(Request $request): ?string
    {
        // Subdomains: {tenant}.palmcore.app
        // Falls back to null in single-tenant or dev environments.
        $host = $request->getHost();
        $parts = explode('.', $host);

        if (count($parts) >= 3) {
            return $parts[0];
        }

        return null;
    }
}
