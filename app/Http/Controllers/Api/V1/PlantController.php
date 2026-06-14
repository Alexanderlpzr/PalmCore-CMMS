<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlantResource;
use App\Models\Plant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('plants.read') && ! $request->user()->tokenCan('*'), 403);

        $query = Plant::query()
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('code');

        $perPage = min((int) ($request->per_page ?? 100), 200);

        return PlantResource::collection(
            $request->has('page')
                ? $query->paginate($perPage)
                : $query->cursorPaginate($perPage)
        );
    }

    public function show(Request $request, string $id): PlantResource
    {
        abort_if(! $request->user()->tokenCan('plants.read') && ! $request->user()->tokenCan('*'), 403);

        return new PlantResource(Plant::findOrFail($id));
    }
}
