<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assets\Enums\ComponentStatus;
use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Services\ComponentLifeHoursService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EquipmentComponentResource;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class EquipmentComponentController extends Controller
{
    public function tree(Request $request, string $equipmentId): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('equipment.read') && ! $request->user()->tokenCan('*'), 403);

        $equipment = Equipment::findOrFail($equipmentId);

        $components = EquipmentComponent::where('equipment_id', $equipment->id)
            ->whereNull('parent_id')
            ->with(['children.children.children'])
            ->get();

        return response()->json(['data' => EquipmentComponentResource::collection($components)]);
    }

    public function index(Request $request, string $equipmentId): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('equipment.read') && ! $request->user()->tokenCan('*'), 403);

        $equipment = Equipment::findOrFail($equipmentId);

        $components = EquipmentComponent::where('equipment_id', $equipment->id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return EquipmentComponentResource::collection($components);
    }

    public function show(Request $request, string $equipmentId, string $id): EquipmentComponentResource
    {
        abort_if(! $request->user()->tokenCan('equipment.read') && ! $request->user()->tokenCan('*'), 403);

        $equipment = Equipment::findOrFail($equipmentId);

        $component = EquipmentComponent::where('equipment_id', $equipment->id)
            ->findOrFail($id);

        return new EquipmentComponentResource($component);
    }

    public function store(Request $request, string $equipmentId): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('equipment.write') && ! $request->user()->tokenCan('*'), 403);

        $tenantId = CurrentTenant::id();
        $equipment = Equipment::findOrFail($equipmentId);

        $data = $request->validate([
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('equipment_components', 'code')->where('equipment_id', $equipment->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'part_number' => ['nullable', 'string', 'max:100'],
            'criticality' => ['nullable', Rule::enum(EquipmentCriticality::class)],
            'status' => ['nullable', Rule::enum(ComponentStatus::class)],
            'worked_hours' => ['nullable', 'numeric', 'min:0'],
            'useful_life_hours' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // worked_hours no se escribe directo: pasa por el servicio para que quede
        // anclado a un punto de partida en el horómetro del equipo. Sin eso, el
        // número que se guarda aquí es el mismo que se queda congelado para siempre.
        $startingHours = $data['worked_hours'] ?? null;
        unset($data['worked_hours']);

        $component = EquipmentComponent::create([
            ...$data,
            'tenant_id' => $tenantId,
            'equipment_id' => $equipment->id,
            'criticality' => $data['criticality'] ?? EquipmentCriticality::Medium->value,
        ]);

        app(ComponentLifeHoursService::class)->initializeBaseline($component, $startingHours);

        return (new EquipmentComponentResource($component))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, string $equipmentId, string $id): EquipmentComponentResource
    {
        abort_if(! $request->user()->tokenCan('equipment.write') && ! $request->user()->tokenCan('*'), 403);

        $equipment = Equipment::findOrFail($equipmentId);

        $component = EquipmentComponent::where('equipment_id', $equipment->id)
            ->findOrFail($id);

        $data = $request->validate([
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('equipment_components', 'code')
                    ->where('equipment_id', $equipment->id)
                    ->ignore($component->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'part_number' => ['nullable', 'string', 'max:100'],
            'criticality' => ['nullable', Rule::enum(EquipmentCriticality::class)],
            'status' => ['nullable', Rule::enum(ComponentStatus::class)],
            'worked_hours' => ['nullable', 'numeric', 'min:0'],
            'useful_life_hours' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Igual que en store(): editar «Horas trabajadas» a mano es una corrección
        // —o el reemplazo físico de la pieza—, y eso también ancla un nuevo punto
        // de partida. Sin pasar por el servicio, el número volvería a quedar
        // congelado desde el momento de esta edición en adelante.
        $hasWorkedHours = array_key_exists('worked_hours', $data);
        $newWorkedHours = $data['worked_hours'] ?? null;
        unset($data['worked_hours']);

        $component->update($data);

        if ($hasWorkedHours) {
            $service = app(ComponentLifeHoursService::class);

            $newWorkedHours !== null
                ? $service->rebaseline($component, (float) $newWorkedHours)
                : $service->clear($component);
        }

        return new EquipmentComponentResource($component->fresh());
    }

    public function destroy(Request $request, string $equipmentId, string $id): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('equipment.write') && ! $request->user()->tokenCan('*'), 403);

        $equipment = Equipment::findOrFail($equipmentId);

        $component = EquipmentComponent::where('equipment_id', $equipment->id)
            ->findOrFail($id);

        $component->delete();

        return response()->json(null, 204);
    }
}
