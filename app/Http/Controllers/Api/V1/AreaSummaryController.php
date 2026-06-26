<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Equipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AreaSummaryController extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('areas.read') && ! $request->user()->tokenCan('*'), 403);

        $area = Area::with('plant')->findOrFail($id);

        $equipment = Equipment::where('area_id', $area->id)
            ->with('kpi')
            ->orderBy('code')
            ->get();

        $kpi = DB::table('equipment_kpis')
            ->whereIn('equipment_id', $equipment->pluck('id'))
            ->selectRaw('
                AVG(availability_percentage) as avg_availability,
                SUM(failure_count) as total_failures,
                AVG(mtbf_hours) as avg_mtbf
            ')
            ->first();

        return response()->json([
            'id' => $area->id,
            'code' => $area->code,
            'name' => $area->name,
            'description' => $area->description,
            'is_active' => $area->is_active,
            'plant' => $area->plant ? [
                'id' => $area->plant->id,
                'code' => $area->plant->code,
                'name' => $area->plant->name,
            ] : null,
            'equipment' => $equipment->map(fn (Equipment $eq) => [
                'id' => $eq->id,
                'code' => $eq->code,
                'name' => $eq->name,
                'status' => $eq->status?->value,
                'criticality' => $eq->criticality?->value,
                'model' => $eq->model,
                'is_active' => $eq->is_active,
                'kpi' => $eq->kpi ? [
                    'availability_percentage' => $eq->kpi->availability_percentage !== null
                        ? round((float) $eq->kpi->availability_percentage, 1) : null,
                    'failure_count' => (int) ($eq->kpi->failure_count ?? 0),
                    'mtbf_hours' => $eq->kpi->mtbf_hours !== null
                        ? round((float) $eq->kpi->mtbf_hours, 1) : null,
                ] : null,
            ])->values(),
            'equipment_count' => $equipment->count(),
            'kpi' => $kpi && $equipment->isNotEmpty() ? [
                'avg_availability' => $kpi->avg_availability !== null ? round((float) $kpi->avg_availability, 1) : null,
                'total_failures' => (int) ($kpi->total_failures ?? 0),
                'avg_mtbf' => $kpi->avg_mtbf !== null ? round((float) $kpi->avg_mtbf, 1) : null,
            ] : null,
        ]);
    }
}
