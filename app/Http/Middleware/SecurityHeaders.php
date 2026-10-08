<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // La cámara solo para el propio sitio: la Portería lee los carnés con ella. Con
        // `camera=()` Chrome la bloqueaba sin preguntar; Safari ignora la cabecera.
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // HSTS: only over real HTTPS connections — never on local HTTP
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // Content Security Policy
        // In local dev, allow Vite dev server (different port = different origin)
        $viteOrigin = app()->environment('local') && file_exists(public_path('hot'))
            ? ' '.trim(file_get_contents(public_path('hot')))
            : '';

        $csp = implode('; ', [
            "default-src 'self'",
            // unsafe-inline required: Filament/Livewire inject inline scripts without a nonce
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'{$viteOrigin}",
            "style-src 'self' 'unsafe-inline'{$viteOrigin}",
            "img-src 'self' data: blob:",
            // fonts.bunny.net serves Instrument Sans (configured in vite.config.js)
            "font-src 'self' data: https://fonts.bunny.net{$viteOrigin}",
            "connect-src 'self' https://fonts.bunny.net{$viteOrigin}".($viteOrigin ? ' '.str_replace('http://', 'ws://', trim($viteOrigin)) : ''),
            "frame-ancestors 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
