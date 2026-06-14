<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EquipmentResource;
use App\Models\Equipment;
use App\Models\EquipmentQrCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EquipmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('equipment.read') && ! $request->user()->tokenCan('*'), 403);

        $query = Equipment::query()
            ->with(['plant', 'area', 'category'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->plant_id, fn ($q, $v) => $q->where('plant_id', $v))
            ->when($request->area_id, fn ($q, $v) => $q->where('area_id', $v))
            ->when($request->criticality, fn ($q, $v) => $q->where('criticality', $v))
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('code');

        $perPage = min((int) ($request->per_page ?? 25), 200);

        return EquipmentResource::collection(
            $request->has('page')
                ? $query->paginate($perPage)
                : $query->cursorPaginate($perPage)
        );
    }

    public function show(Request $request, string $id): EquipmentResource
    {
        abort_if(! $request->user()->tokenCan('equipment.read') && ! $request->user()->tokenCan('*'), 403);

        $equipment = Equipment::with(['plant', 'area', 'category'])->findOrFail($id);

        return new EquipmentResource($equipment);
    }

    public function byQrToken(Request $request, string $qrToken): EquipmentResource
    {
        abort_if(! $request->user()->tokenCan('equipment.read') && ! $request->user()->tokenCan('*'), 403);

        $qrCode = EquipmentQrCode::where('qr_token', $qrToken)
            ->where('is_active', true)
            ->firstOrFail();

        $qrCode->recordScan();

        $equipment = Equipment::with(['plant', 'area', 'category'])->findOrFail($qrCode->equipment_id);

        return new EquipmentResource($equipment);
    }
}
