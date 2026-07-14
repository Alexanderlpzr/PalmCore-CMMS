<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Services\DowntimeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ClassifyDowntimeEventRequest;
use App\Http\Requests\Api\V1\ConfirmDowntimeEventRequest;
use App\Http\Requests\Api\V1\DisputeDowntimeEventRequest;
use App\Http\Requests\Api\V1\EndDowntimeEventRequest;
use App\Http\Requests\Api\V1\StoreDowntimeEventRequest;
use App\Http\Resources\Api\V1\DowntimeEventResource;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class DowntimeEventController extends Controller
{
    public function __construct(private readonly DowntimeService $service) {}

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
            ->when($request->plant_id, fn ($q, $v) => $q->where('plant_id', $v))
            ->when($request->stoppage_category, fn ($q, $v) => $q->where('stoppage_category', $v))
            ->when($request->boolean('ongoing'), fn ($q) => $q->ongoing())
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

    /**
     * Register a paro. Most stoppages in a palm-oil plant never produce a work
     * order — this is the door they come in through.
     */
    public function store(StoreDowntimeEventRequest $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = [
            ...$request->validated(),
            'tenant_id' => $request->user()->currentAccessToken()->tenant_id,
        ];

        // With no ended_at the paro is still happening: it opens and stays open.
        $event = $this->service->register($data, $request->user());

        return (new DowntimeEventResource($event->load('equipment')))
            ->response()
            ->setStatusCode(201);
    }

    /** Close an open paro: the line is back up. */
    public function end(EndDowntimeEventRequest $request, string $id): DowntimeEventResource
    {
        $this->authorizeWrite($request);

        $event = EquipmentDowntimeEvent::findOrFail($id);

        $event = $this->service->end(
            $event,
            $request->validated('ended_at'),
            $request->validated('notes'),
        );

        return new DowntimeEventResource($event->load('equipment'));
    }

    /**
     * A4 — el técnico afina el Tipo I cuando ya sabe qué se rompió. El paro que la
     * OT abrió en «otro» deja de contaminar el Pareto.
     */
    public function classify(ClassifyDowntimeEventRequest $request, string $id): DowntimeEventResource
    {
        $this->authorizeWrite($request);

        $event = EquipmentDowntimeEvent::findOrFail($id);

        $event = $this->service->reclassify(
            $event,
            StoppageCategory::from($request->validated('stoppage_category')),
            $request->validated('stoppage_cause'),
        );

        return new DowntimeEventResource($event->load('equipment'));
    }

    /** A5 — producción firma las horas que se le restan a la planta. */
    public function confirm(ConfirmDowntimeEventRequest $request, string $id): DowntimeEventResource
    {
        $this->authorizeWrite($request);

        $event = EquipmentDowntimeEvent::findOrFail($id);

        $event = $this->service->confirm($event, $request->user(), $request->validated('notes'));

        return new DowntimeEventResource($event->load(['equipment', 'confirmedBy']));
    }

    /** A5 — producción no está de acuerdo. El paro no se borra: queda en disputa. */
    public function dispute(DisputeDowntimeEventRequest $request, string $id): DowntimeEventResource
    {
        $this->authorizeWrite($request);

        $event = EquipmentDowntimeEvent::findOrFail($id);

        $event = $this->service->dispute($event, $request->user(), $request->validated('reason'));

        return new DowntimeEventResource($event->load(['equipment', 'confirmedBy']));
    }

    /**
     * Hours of production lost in a window, split by Tipo I. The number the plant
     * argues about every Monday.
     */
    public function lostHours(Request $request, string $plant): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('downtime.read') && ! $request->user()->tokenCan('*'), 403);

        $plant = Plant::findOrFail($plant);

        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : now();

        $byCategory = $this->service->lostHoursByCategory($plant->id, $from, $to);

        return response()->json([
            'data' => [
                'from' => $from->toISOString(),
                'to' => $to->toISOString(),
                'total_hours' => round(array_sum($byCategory), 2),
                // A5 — cuánto de este número todavía no lo firmó producción. No es
                // un descuento: es la parte que hoy se sostiene en una sola palabra.
                'pending_confirmation' => $this->service->pendingConfirmation($plant->id, $from, $to),
                'by_category' => collect($byCategory)
                    ->map(fn (float $hours, string $category): array => [
                        'category' => $category,
                        'label' => StoppageCategory::from($category)->label(),
                        'color' => StoppageCategory::from($category)->color(),
                        'is_maintenance_responsibility' => StoppageCategory::from($category)->isMaintenanceResponsibility(),
                        'hours' => $hours,
                    ])
                    ->sortByDesc('hours')
                    ->values(),
            ],
        ]);
    }

    /**
     * A6 — el Pareto de horas perdidas por equipo. Dónde está el 80 %.
     */
    public function lostHoursByEquipment(Request $request, string $plant): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('downtime.read') && ! $request->user()->tokenCan('*'), 403);

        $plant = Plant::findOrFail($plant);

        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : now();

        return response()->json([
            'data' => [
                'from' => $from->toISOString(),
                'to' => $to->toISOString(),
                ...$this->service->lostHoursByEquipment($plant->id, $from, $to),
            ],
        ]);
    }

    private function authorizeWrite(Request $request): void
    {
        abort_if(
            ! $request->user()->tokenCan('downtime.write') && ! $request->user()->tokenCan('*'),
            403,
        );
    }
}
