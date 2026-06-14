<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DowntimeEventResource;
use App\Models\EquipmentDowntimeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DowntimeEventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('downtime.read') && ! $request->user()->tokenCan('*'), 403);

        $query = EquipmentDowntimeEvent::query()
            ->with(['equipment'])
            ->when($request->equipment_id, fn ($q, $v) => $q->where('equipment_id', $v))
            ->when(
                $request->has('was_planned'),
                fn ($q) => $q->where('was_planned', filter_var($request->was_planned, FILTER_VALIDATE_BOOLEAN))
            )
            ->when($request->from, fn ($q, $v) => $q->where('started_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->where('started_at', '<=', $v))
            ->orderByDesc('started_at');

        $perPage = min((int) ($request->per_page ?? 25), 200);

        return DowntimeEventResource::collection(
            $request->has('page')
                ? $query->paginate($perPage)
                : $query->cursorPaginate($perPage)
        );
    }

    public function show(Request $request, string $id): DowntimeEventResource
    {
        abort_if(! $request->user()->tokenCan('downtime.read') && ! $request->user()->tokenCan('*'), 403);

        $event = EquipmentDowntimeEvent::with(['equipment'])->findOrFail($id);

        return new DowntimeEventResource($event);
    }
}
