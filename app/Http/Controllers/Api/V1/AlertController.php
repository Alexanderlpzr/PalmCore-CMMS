<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertStatus;
use App\Domain\Alerts\Services\AlertService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AlertResource;
use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AlertController extends Controller
{
    public function __construct(private readonly AlertService $alertService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Alert::query()
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->severity, fn ($q, $v) => $q->where('severity', $v))
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->orderByDesc('created_at');

        $perPage = min((int) ($request->per_page ?? 25), 100);

        return AlertResource::collection($query->cursorPaginate($perPage));
    }

    public function count(Request $request): JsonResponse
    {
        $count = Alert::query()
            ->where('status', $request->status ?? AlertStatus::Open->value)
            ->when($request->severity, fn ($q, $v) => $q->where('severity', $v))
            ->count();

        return response()->json(['count' => $count]);
    }

    public function resolve(Request $request, string $id): JsonResponse
    {
        $alert = Alert::findOrFail($id);

        $resolved = $this->alertService->resolve($alert, $request->user());

        return response()->json([
            'status' => $resolved ? 'resolved' : 'already_closed',
        ]);
    }

    public function dismiss(Request $request, string $id): JsonResponse
    {
        $alert = Alert::findOrFail($id);

        if ($alert->severity === AlertSeverity::Critical) {
            return response()->json(['message' => 'cannot_dismiss_critical'], 422);
        }

        $dismissed = $this->alertService->dismiss($alert, $request->user());

        return response()->json([
            'status' => $dismissed ? 'dismissed' : 'already_closed',
        ]);
    }
}
