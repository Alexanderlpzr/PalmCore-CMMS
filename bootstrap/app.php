<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\RedirectToCanonicalDomain;
use App\Http\Middleware\ResolveApiTenant;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Railway's load balancer so Laravel reads X-Forwarded-Proto: https
        // and generates https:// URLs for assets, redirects, and cookies.
        $middleware->trustProxies(at: '*');

        // Canónico primero: www → dominio pelado antes de cualquier otra cosa.
        $middleware->prepend(RedirectToCanonicalDomain::class);

        $middleware->append(SecurityHeaders::class);

        $middleware->api(append: [
            HandleCors::class,
            LogApiRequest::class,
        ]);

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'api.tenant' => ResolveApiTenant::class,
            'idempotency' => IdempotencyMiddleware::class,
            'super-admin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'detail' => $e->detail,
            ], 409);
        });
    })->create();
