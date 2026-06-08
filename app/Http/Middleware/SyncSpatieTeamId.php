<?php

namespace App\Http\Middleware;

use App\Infrastructure\Tenancy\CurrentTenant;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncSpatieTeamId
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($tenant = Filament::getTenant()) {
            // Sync both systems so TenantScope and Spatie Teams work within Filament panel.
            CurrentTenant::set($tenant);
            setPermissionsTeamId($tenant->id);
        }

        $response = $next($request);

        CurrentTenant::clear();

        return $response;
    }
}
