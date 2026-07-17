<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un solo dominio canónico: el pelado. Si la visita llega por `www.`, la manda
 * al mismo camino sin `www.` con un 301.
 *
 * El proxy (Caddy) es el lugar «correcto» para esto, pero su config se monta como
 * archivo y en este despliegue no siempre recarga; el request igual llega hasta
 * la app, así que resolverlo aquí lo hace confiable pase lo que pase con el proxy.
 * No hardcodea el dominio: quita el prefijo `www.` de cualquier host, así en local
 * (sin www) es un no-op.
 *
 * Solo GET/HEAD: un 301 sobre un POST perdería el cuerpo, y los enlaces externos
 * que traen gente por www —Google, marcadores— son siempre GET.
 */
class RedirectToCanonicalDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if (str_starts_with($host, 'www.') && in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            $canonicalHost = substr($host, 4);
            $target = $request->getScheme().'://'.$canonicalHost.$request->getRequestUri();

            return redirect($target, 301);
        }

        return $next($request);
    }
}
