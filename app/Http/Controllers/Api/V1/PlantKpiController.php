<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Analytics\Services\PlantKpiService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpsertProductionCalendarRequest;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\ProductionCalendarDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The plant's headline number and the calendar that makes it computable.
 */
class PlantKpiController extends Controller
{
    public function __construct(private readonly PlantKpiService $service) {}

    /** Eficiencia, horas perdidas, MTBF y MTTR de PLANTA en una ventana. */
    public function show(Request $request, string $plant): JsonResponse
    {
        $this->authorizeRead($request);

        $plant = Plant::findOrFail($plant);

        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'))
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))
            : now()->endOfMonth();

        return response()->json([
            'data' => [
                'plant_id' => $plant->id,
                'from' => $from->toISOString(),
                'to' => $to->toISOString(),
                ...$this->service->calculate($plant, $from, $to),
            ],
        ]);
    }

    /** Los meses ya cerrados: la serie que la gerencia mira. */
    public function history(Request $request, string $plant): JsonResponse
    {
        $this->authorizeRead($request);

        $plant = Plant::findOrFail($plant);

        $months = PlantMonthlyKpi::where('plant_id', $plant->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(min((int) $request->query('limit', 12), 36))
            ->get();

        return response()->json([
            'data' => $months->map(fn (PlantMonthlyKpi $kpi): array => [
                'period' => $kpi->periodLabel(),
                'year' => $kpi->year,
                'month' => $kpi->month,
                'programmed_hours' => $kpi->programmed_hours,
                'lost_hours' => $kpi->lost_hours,
                'effective_hours' => $kpi->effective_hours,
                'maintenance_lost_hours' => $kpi->maintenance_lost_hours,
                'efficiency_percentage' => $kpi->efficiency_percentage,
                'failure_count' => $kpi->failure_count,
                'mtbf_hours' => $kpi->mtbf_hours,
                'mttr_hours' => $kpi->mttr_hours,
            ])->reverse()->values(),
        ]);
    }

    /** El calendario de producción de un mes: el denominador de la eficiencia. */
    public function calendar(Request $request, string $plant): JsonResponse
    {
        $this->authorizeRead($request);

        $plant = Plant::findOrFail($plant);

        $month = $request->filled('month')
            ? Carbon::parse($request->query('month'))->startOfMonth()
            : now()->startOfMonth();

        $days = ProductionCalendarDay::where('plant_id', $plant->id)
            ->whereBetween('calendar_date', [
                $month->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->orderBy('calendar_date')
            ->get();

        return response()->json([
            'data' => $days->map(fn (ProductionCalendarDay $day): array => [
                'id' => $day->id,
                'calendar_date' => $day->calendar_date->toDateString(),
                'programmed_hours' => $day->programmed_hours,
                'notes' => $day->notes,
            ])->values(),
            'meta' => [
                'month' => $month->format('Y-m'),
                'programmed_hours' => round((float) $days->sum('programmed_hours'), 2),
            ],
        ]);
    }

    /**
     * The planner fills the month in one go. An upsert per day, so correcting a
     * single day never wipes the rest of the month.
     */
    public function upsertCalendar(UpsertProductionCalendarRequest $request, string $plant): JsonResponse
    {
        $this->authorizeWrite($request);

        $plant = Plant::findOrFail($plant);

        foreach ($request->validated('days') as $day) {
            ProductionCalendarDay::updateOrCreate(
                [
                    'plant_id' => $plant->id,
                    'calendar_date' => $day['calendar_date'],
                ],
                [
                    'tenant_id' => $plant->tenant_id,
                    'programmed_hours' => $day['programmed_hours'],
                    'notes' => $day['notes'] ?? null,
                ],
            );
        }

        return response()->json([
            'meta' => ['saved' => count($request->validated('days'))],
        ], 200);
    }

    private function authorizeRead(Request $request): void
    {
        abort_if(
            ! $request->user()->tokenCan('reliability.read') && ! $request->user()->tokenCan('*'),
            403,
        );
    }

    private function authorizeWrite(Request $request): void
    {
        abort_if(
            ! $request->user()->tokenCan('plants.write') && ! $request->user()->tokenCan('*'),
            403,
        );
    }
}
