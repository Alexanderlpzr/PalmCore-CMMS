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

        // La empresa no se limpia aquí, después de `$next`: en cada clic del panel
        // —acciones, filtros, paginación— Livewire corre este middleware como
        // persistente, con una petición falsa que vuelve en el acto, y sigue con la acción
        // fuera de él. Limpiar aquí dejaba la acción sin empresa: los registros nacían sin
        // `tenant_id` (las bonificaciones del trabajador fallaban así) y TenantScope no
        // filtraba. Se limpia en terminate(), cuando la respuesta ya salió.
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        CurrentTenant::clear();
    }
}
