<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ComponentHistoryResource;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComponentHistoryController extends Controller
{
    public function index(Request $request, string $equipmentId, string $componentId): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('equipment.read') && ! $request->user()->tokenCan('*'), 403);

        $equipment = Equipment::findOrFail($equipmentId);
        $component = EquipmentComponent::where('equipment_id', $equipment->id)->findOrFail($componentId);

        $history = $component->history()->with('user')->paginate(15);

        return ComponentHistoryResource::collection($history)->response();
    }

    public function store(Request $request, string $equipmentId, string $componentId): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('equipment.write') && ! $request->user()->tokenCan('*'), 403);

        $equipment = Equipment::findOrFail($equipmentId);
        $component = EquipmentComponent::where('equipment_id', $equipment->id)->findOrFail($componentId);

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(['installation', 'maintenance', 'inspection', 'failure', 'replacement', 'repair', 'retirement', 'note'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'worked_hours_at_event' => ['nullable', 'numeric', 'min:0'],
            'occurred_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]);

        $data['occurred_at'] ??= now();
        $data['user_id'] = $request->user()->id;

        $entry = $component->history()->create($data);
        $entry->load('user');

        return (new ComponentHistoryResource($entry))->response()->setStatusCode(201);
    }
}
