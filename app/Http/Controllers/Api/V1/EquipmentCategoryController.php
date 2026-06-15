<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EquipmentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('equipment.read') && ! $request->user()->tokenCan('*'), 403);

        $categories = EquipmentCategory::query()
            ->where('is_active', true)
            ->when($request->boolean('component_types'), fn ($q) => $q->where('is_component_type', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'color', 'is_component_type']);

        return response()->json(['data' => $categories->values()]);
    }
}
