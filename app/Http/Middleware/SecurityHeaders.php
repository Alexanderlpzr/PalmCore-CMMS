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
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // HSTS: only over real HTTPS connections — never on local HTTP
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // Content Security Policy
        // Using a permissive baseline that covers Filament (Alpine, Livewire, inline styles)
        // with report-uri to observe violations before tightening in a future sprint.
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        // In local dev, allow Vite dev server (different port = different origin)
        $viteOrigin = app()->environment('local') && file_exists(public_path('hot'))
            ? ' '.trim(file_get_contents(public_path('hot')))
            : '';

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'{$viteOrigin}",  // unsafe-eval required by Alpine.js
            "style-src 'self' 'unsafe-inline'{$viteOrigin}",                  // Filament uses inline styles
            "img-src 'self' data: blob:",
            "font-src 'self' data:{$viteOrigin}",
            "connect-src 'self'{$viteOrigin}".($viteOrigin ? ' '.str_replace('http://', 'ws://', trim($viteOrigin)) : ''),
            "frame-ancestors 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
