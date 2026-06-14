<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EquipmentKpiResource;
use App\Models\EquipmentKpi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EquipmentKpiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('reliability.read') && ! $request->user()->tokenCan('*'), 403);

        $query = EquipmentKpi::query()
            ->with(['equipment'])
            ->when($request->equipment_id, fn ($q, $v) => $q->where('equipment_id', $v))
            ->orderByDesc('updated_at');

        $perPage = min((int) ($request->per_page ?? 25), 200);

        return EquipmentKpiResource::collection(
            $request->has('page')
                ? $query->paginate($perPage)
                : $query->cursorPaginate($perPage)
        );
    }

    public function show(Request $request, string $id): EquipmentKpiResource
    {
        abort_if(! $request->user()->tokenCan('reliability.read') && ! $request->user()->tokenCan('*'), 403);

        $kpi = EquipmentKpi::with(['equipment'])->findOrFail($id);

        return new EquipmentKpiResource($kpi);
    }
}
