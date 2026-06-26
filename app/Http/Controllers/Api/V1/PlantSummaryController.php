<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Plant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlantSummaryController extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('plants.read') && ! $request->user()->tokenCan('*'), 403);

        $plant = Plant::with('areas')->findOrFail($id);

        $areaIds = $plant->areas->pluck('id');

        // Single query: equipment count per area
        $countByArea = DB::table('equipment')
            ->whereIn('area_id', $areaIds)
            ->whereNull('deleted_at')
            ->select('area_id', DB::raw('count(*) as total'))
            ->groupBy('area_id')
            ->pluck('total', 'area_id');

        // Single query: KPI aggregates for all equipment in this plant
        $kpi = DB::table('equipment_kpis')
            ->join('equipment', 'equipment_kpis.equipment_id', '=', 'equipment.id')
            ->where('equipment.plant_id', $plant->id)
            ->whereNull('equipment.deleted_at')
            ->selectRaw('
                AVG(equipment_kpis.availability_percentage) as avg_availability,
                SUM(equipment_kpis.failure_count) as total_failures,
                AVG(equipment_kpis.mtbf_hours) as avg_mtbf
            ')
            ->first();

        $areas = $plant->areas
            ->sortBy('sort_order')
            ->values()
            ->map(fn (Area $area) => [
                'id' => $area->id,
                'code' => $area->code,
                'name' => $area->name,
                'description' => $area->description,
                'equipment_count' => (int) $countByArea->get($area->id, 0),
            ]);

        return response()->json([
            'id' => $plant->id,
            'code' => $plant->code,
            'name' => $plant->name,
            'address' => $plant->address,
            'city' => $plant->city,
            'state_province' => $plant->state_province,
            'country_code' => $plant->country_code,
            'latitude' => $plant->latitude,
            'longitude' => $plant->longitude,
            'is_active' => $plant->is_active,
            'areas' => $areas,
            'equipment_count' => (int) $countByArea->sum(),
            'kpi' => $kpi ? [
                'avg_availability' => $kpi->avg_availability !== null ? round((float) $kpi->avg_availability, 1) : null,
                'total_failures' => (int) ($kpi->total_failures ?? 0),
                'avg_mtbf' => $kpi->avg_mtbf !== null ? round((float) $kpi->avg_mtbf, 1) : null,
            ] : null,
        ]);
    }
}
