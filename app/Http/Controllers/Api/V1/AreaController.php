<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AreaResource;
use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AreaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('areas.read') && ! $request->user()->tokenCan('*'), 403);

        $query = Area::query()
            ->with(['plant'])
            ->when($request->plant_id, fn ($q, $v) => $q->where('plant_id', $v))
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('code');

        $perPage = min((int) ($request->per_page ?? 100), 200);

        return AreaResource::collection(
            $request->has('page')
                ? $query->paginate($perPage)
                : $query->cursorPaginate($perPage)
        );
    }

    public function show(Request $request, string $id): AreaResource
    {
        abort_if(! $request->user()->tokenCan('areas.read') && ! $request->user()->tokenCan('*'), 403);

        $area = Area::with(['plant'])->findOrFail($id);

        return new AreaResource($area);
    }
}
