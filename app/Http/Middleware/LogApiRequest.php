<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

use function Illuminate\Support\defer;

class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $exception = null;
        $response = null;

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $statusCode = match (true) {
            $response !== null => $response->getStatusCode(),
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };

        $token = $request->user()?->currentAccessToken();

        defer(function () use ($request, $statusCode, $durationMs, $token): void {
            ApiRequestLog::create([
                'tenant_id' => $token?->tenant_id ?? null,
                'user_id' => $request->user()?->id,
                'token_id' => $token?->id,
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'status_code' => $statusCode,
                'duration_ms' => $durationMs,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);
        })->always();

        if ($exception !== null) {
            throw $exception;
        }

        return $response;
    }
}
