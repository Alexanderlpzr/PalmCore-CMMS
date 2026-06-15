<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MaintenancePlanResource;
use App\Models\MaintenancePlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaintenancePlanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_if(
            ! $request->user()->tokenCan('maintenance-plans.read') && ! $request->user()->tokenCan('*'),
            403
        );

        $query = MaintenancePlan::query()
            ->with(['equipment', 'schedule'])
            ->when($request->equipment_id, fn ($q, $v) => $q->where('equipment_id', $v))
            ->when($request->trigger_source, fn ($q, $v) => $q->where('trigger_source', $v))
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('plan_number');

        $perPage = min((int) ($request->per_page ?? 25), 200);

        return MaintenancePlanResource::collection(
            $request->has('page')
                ? $query->paginate($perPage)
                : $query->cursorPaginate($perPage)
        );
    }

    public function show(Request $request, string $id): MaintenancePlanResource
    {
        abort_if(
            ! $request->user()->tokenCan('maintenance-plans.read') && ! $request->user()->tokenCan('*'),
            403
        );

        $plan = MaintenancePlan::with([
            'equipment',
            'schedule',
            'tasks',
            'responsibleUser',
        ])->findOrFail($id);

        return new MaintenancePlanResource($plan);
    }
}
