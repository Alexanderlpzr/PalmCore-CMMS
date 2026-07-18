<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Analytics\Services\ExecutiveDashboardService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only executive dashboard KPI endpoints.
 *
 * All methods are scoped to the current tenant, require the `reliability.read`
 * ability (or wildcard `*`), and are cached for 5 minutes to reduce DB load.
 * The actual calculation lives in ExecutiveDashboardService, so the Filament
 * "Resumen Ejecutivo" page can reuse the exact same numbers without a second
 * implementation of these queries.
 */
class ExecutiveDashboardController extends Controller
{
    public function __construct(private readonly ExecutiveDashboardService $service) {}

    public function summary(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('reliability.read') && ! $request->user()->tokenCan('*'), 403);

        $data = Cache::remember(
            'executive:summary:'.CurrentTenant::id(),
            300,
            fn () => $this->service->summary(CurrentTenant::id())
        );

        return response()->json($data);
    }

    public function areas(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('reliability.read') && ! $request->user()->tokenCan('*'), 403);

        $data = Cache::remember(
            'executive:areas:'.CurrentTenant::id(),
            300,
            fn () => ['data' => $this->service->areas(CurrentTenant::id())]
        );

        return response()->json($data);
    }

    public function topEquipment(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('reliability.read') && ! $request->user()->tokenCan('*'), 403);

        $data = Cache::remember(
            'executive:topEquipment:'.CurrentTenant::id(),
            300,
            fn () => ['data' => $this->service->topEquipment(CurrentTenant::id())]
        );

        return response()->json($data);
    }

    public function costs(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('reliability.read') && ! $request->user()->tokenCan('*'), 403);

        $data = Cache::remember(
            'executive:costs:'.CurrentTenant::id(),
            300,
            fn () => $this->service->costs(CurrentTenant::id())
        );

        return response()->json($data);
    }

    public function trends(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('reliability.read') && ! $request->user()->tokenCan('*'), 403);

        $data = Cache::remember(
            'executive:trends:'.CurrentTenant::id(),
            300,
            fn () => ['data' => $this->service->trends(CurrentTenant::id())]
        );

        return response()->json($data);
    }
}
