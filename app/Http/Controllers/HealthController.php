<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class HealthController
{
    public function __invoke(Request $request): JsonResponse
    {
        // Serve from cache if available (best-effort — cache may be down)
        $cached = null;
        try {
            $cached = Cache::get('health:result');
        } catch (\Throwable) {
            // Cache unavailable — proceed with fresh checks
        }

        if ($cached !== null) {
            $statusCode = $cached['status'] === 'ok' ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

            return response()->json($cached, $statusCode);
        }

        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        $result = [
            'status' => in_array(false, $checks, strict: true) ? 'degraded' : 'ok',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            Cache::put('health:result', $result, 15);
        } catch (\Throwable) {
            // Cache unavailable — return fresh result without caching
        }

        $statusCode = $result['status'] === 'ok' ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return response()->json($result, $statusCode);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            Cache::put('health:ping', 'pong', 10);

            return Cache::get('health:ping') === 'pong';
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkQueue(): bool
    {
        if (config('queue.default') === 'sync') {
            return true;
        }
        try {
            Queue::size('default');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        try {
            Storage::disk('local')->put('health-check.tmp', 'ok');
            $exists = Storage::disk('local')->exists('health-check.tmp');
            Storage::disk('local')->delete('health-check.tmp');

            return $exists;
        } catch (\Throwable) {
            return false;
        }
    }
}
