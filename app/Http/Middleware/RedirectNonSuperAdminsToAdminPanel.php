<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quien no es superadministrador y abre la plataforma va a su propio panel, no a un
 * 403 en blanco. Pasaba sobre todo al salir de la plataforma: eso deja en su pantalla
 * de entrada, y si ahí entraba el administrador de una empresa, terminaba bloqueado
 * en /platform sin salida a la vista.
 *
 * No ve nada de la plataforma por esto: solo se redirigen las visitas a páginas.
 * Cualquier otra petición —las de Livewire, las que esperan JSON— sigue topando con
 * EnsureSuperAdmin, que va detrás.
 */
class RedirectNonSuperAdminsToAdminPanel
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_super_admin && $request->isMethod('GET') && ! $request->expectsJson()) {
            return redirect()->to(url(Filament::getPanel('admin')->getPath()));
        }

        return $next($request);
    }
}
