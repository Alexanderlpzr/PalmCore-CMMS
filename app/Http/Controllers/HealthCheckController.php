<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        // ── Database ─────────────────────────────────────────────────────────
        $checks['database'] = $this->check(fn () => DB::connection()->getPdo() !== null);

        // ── Cache ────────────────────────────────────────────────────────────
        $checks['cache'] = $this->check(function () {
            $key = 'health:ping:'.now()->timestamp;
            Cache::put($key, 1, 5);

            return Cache::get($key) === 1;
        });

        // ── Queue ────────────────────────────────────────────────────────────
        $checks['queue'] = $this->check(function () {
            // Check that the jobs table is reachable and not severely backed up
            $pending = DB::table('jobs')->count();

            return $pending < 1000;
        });

        // ── Storage ──────────────────────────────────────────────────────────
        $checks['storage'] = $this->check(function () {
            $path = storage_path('app/.health');
            file_put_contents($path, now()->toISOString());

            return file_exists($path);
        });

        foreach ($checks as $check) {
            if ($check['status'] !== 'ok') {
                $healthy = false;
            }
        }

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toISOString(),
            'version' => config('palmcore.version', '0.0.0'),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function check(callable $fn): array
    {
        try {
            $ok = $fn();

            return ['status' => $ok ? 'ok' : 'fail'];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }
}
