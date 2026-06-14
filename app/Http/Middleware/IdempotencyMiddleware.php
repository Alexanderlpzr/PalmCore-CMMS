<?php

namespace App\Http\Middleware;

use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if ($key === null) {
            return $next($request);
        }

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $key)) {
            return response()->json(['message' => 'Idempotency-Key must be a valid UUID v4.'], 422);
        }

        $tenantId = CurrentTenant::id();
        $fingerprint = hash('sha256', $request->method().$request->path().$request->getContent());

        $existing = IdempotencyKey::where('tenant_id', $tenantId)
            ->where('idempotency_key', $key)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            if ($existing->request_fingerprint !== $fingerprint) {
                return response()->json([
                    'message' => 'Idempotency-Key already used with a different request.',
                ], 422);
            }

            return response()->json($existing->response_body, $existing->response_status)
                ->header('Idempotency-Replayed', 'true');
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            try {
                IdempotencyKey::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $request->user()?->id,
                    'idempotency_key' => $key,
                    'request_fingerprint' => $fingerprint,
                    'response_status' => $response->getStatusCode(),
                    'response_body' => json_decode($response->getContent(), true),
                    'expires_at' => now()->addHours(24),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Race condition: another process stored the same key — return their result.
                $existing = IdempotencyKey::where('tenant_id', $tenantId)
                    ->where('idempotency_key', $key)
                    ->firstOrFail();

                return response()->json($existing->response_body, $existing->response_status)
                    ->header('Idempotency-Replayed', 'true');
            }
        }

        return $response;
    }
}
