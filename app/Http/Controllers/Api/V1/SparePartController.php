<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SparePartResource;
use App\Models\SparePart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SparePartController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('inventory.read') && ! $request->user()->tokenCan('*'), 403);

        $query = SparePart::query()
            ->with(['manufacturer'])
            ->when($request->category_type, fn ($q, $v) => $q->where('category_type', $v))
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('code');

        $perPage = min((int) ($request->per_page ?? 25), 200);

        return SparePartResource::collection(
            $request->has('page')
                ? $query->paginate($perPage)
                : $query->cursorPaginate($perPage)
        );
    }

    public function show(Request $request, string $id): SparePartResource
    {
        abort_if(! $request->user()->tokenCan('inventory.read') && ! $request->user()->tokenCan('*'), 403);

        $sparePart = SparePart::with(['manufacturer'])->findOrFail($id);

        return new SparePartResource($sparePart);
    }
}
