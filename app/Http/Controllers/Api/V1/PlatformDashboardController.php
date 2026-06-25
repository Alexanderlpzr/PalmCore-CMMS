<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Analytics\Services\PlatformAnalyticsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Super Admin platform dashboard — cross-tenant aggregated metrics.
 *
 * Reached only behind auth:sanctum + the super-admin middleware, and never the
 * api.tenant scope (these endpoints are deliberately platform-wide). Results are
 * cached for 5 minutes inside PlatformAnalyticsService.
 */
class PlatformDashboardController extends Controller
{
    public function __construct(private readonly PlatformAnalyticsService $analytics) {}

    public function summary(): JsonResponse
    {
        return response()->json($this->analytics->summary());
    }

    public function analytics(): JsonResponse
    {
        return response()->json($this->analytics->analytics());
    }
}
