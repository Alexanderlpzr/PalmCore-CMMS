<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Maintenance\Enums\MeterReadingUnit;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBulkMeterReadingsRequest;
use App\Http\Requests\Api\V1\StoreMeterReadingRequest;
use App\Http\Resources\Api\V1\MeterReadingResource;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use App\Models\MaintenancePlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The daily round: one operator, ~30 dials, one pass through the plant.
 */
class MeterReadingController extends Controller
{
    public function __construct(private readonly EquipmentMeterReadingService $service) {}

    public function index(Request $request, string $equipment): JsonResponse
    {
        $this->authorizeRead($request);

        $equipment = Equipment::findOrFail($equipment);

        $readings = EquipmentMeterReading::where('equipment_id', $equipment->id)
            ->with('recordedBy')
            ->orderByDesc('recorded_at')
            ->limit(min((int) $request->query('limit', 50), 200))
            ->get();

        return response()->json([
            'data' => MeterReadingResource::collection($readings)->resolve(),
            'meta' => [
                'current_reading' => $this->service->currentReading($equipment),
                'accumulated_reading' => $this->service->accumulatedReading($equipment),
                'consumption_per_day' => $this->service->consumptionPerDay($equipment),
                'unit' => $equipment->meter_unit?->value,
            ],
        ]);
    }

    public function store(StoreMeterReadingRequest $request, string $equipment): JsonResponse
    {
        $this->authorizeWrite($request);

        $equipment = Equipment::findOrFail($equipment);

        $reading = $this->service->record(
            equipment: $equipment,
            readingValue: (float) $request->validated('reading_value'),
            recordedBy: $request->user(),
            unit: $request->filled('reading_unit')
                ? MeterReadingUnit::from($request->validated('reading_unit'))
                : ($equipment->meter_unit ?? MeterReadingUnit::Hours),
            recordedAt: $request->filled('recorded_at')
                ? Carbon::parse($request->validated('recorded_at'))
                : null,
            notes: $request->validated('notes'),
        );

        return (new MeterReadingResource($reading))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * The whole round in one request. A bad reading on one dial must not lose the
     * other 29, so each row is reported on its own.
     */
    public function bulk(StoreBulkMeterReadingsRequest $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $result = $this->service->recordBulk($request->validated('readings'), $request->user());

        return response()->json([
            'data' => MeterReadingResource::collection(collect($result['recorded']))->resolve(),
            'meta' => [
                'recorded' => count($result['recorded']),
                'failed' => $result['failed'],
            ],
        ], 201);
    }

    /** «Días faltantes» — how long until this plan falls due at the current pace. */
    public function projection(Request $request, string $equipment): JsonResponse
    {
        $this->authorizeRead($request);

        $equipment = Equipment::findOrFail($equipment);

        $plans = MaintenancePlan::where('equipment_id', $equipment->id)
            ->where('is_active', true)
            ->with('schedule')
            ->get();

        return response()->json([
            'data' => $plans->map(fn (MaintenancePlan $plan): array => [
                'maintenance_plan_id' => $plan->id,
                'plan_number' => $plan->plan_number,
                'name' => $plan->name,
                'next_due_meter' => $plan->schedule?->next_due_meter,
                'next_due_at' => $plan->schedule?->next_due_at?->toISOString(),
                'days_until_due' => $this->service->daysUntilDue($equipment, $plan),
            ])->values(),
            'meta' => [
                'accumulated_reading' => $this->service->accumulatedReading($equipment),
                'consumption_per_day' => $this->service->consumptionPerDay($equipment),
            ],
        ]);
    }

    private function authorizeRead(Request $request): void
    {
        abort_if(
            ! $request->user()->tokenCan('equipment.read') && ! $request->user()->tokenCan('*'),
            403,
        );
    }

    private function authorizeWrite(Request $request): void
    {
        abort_if(
            ! $request->user()->tokenCan('equipment.write') && ! $request->user()->tokenCan('*'),
            403,
        );
    }
}
