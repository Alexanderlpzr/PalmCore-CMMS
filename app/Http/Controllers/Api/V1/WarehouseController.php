<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('inventory.read') && ! $request->user()->tokenCan('*'), 403);

        $query = Warehouse::query()
            ->selectRaw('
                warehouses.*,
                (
                    SELECT COUNT(*)
                    FROM warehouse_spare_parts wsp
                    WHERE wsp.warehouse_id = warehouses.id
                      AND wsp.current_stock > 0
                ) as items_count,
                (
                    SELECT COALESCE(SUM(wsp.current_stock * wsp.average_unit_cost), 0)
                    FROM warehouse_spare_parts wsp
                    WHERE wsp.warehouse_id = warehouses.id
                ) as total_inventory_value,
                (
                    SELECT COUNT(*)
                    FROM warehouse_spare_parts wsp
                    INNER JOIN spare_parts sp ON sp.id = wsp.spare_part_id
                    WHERE wsp.warehouse_id = warehouses.id
                      AND sp.minimum_stock IS NOT NULL
                      AND wsp.current_stock < sp.minimum_stock
                ) as low_stock_count
            ')
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('name');

        $perPage = min((int) ($request->per_page ?? 50), 200);

        return WarehouseResource::collection(
            $request->has('page')
                ? $query->paginate($perPage)
                : $query->cursorPaginate($perPage)
        );
    }

    public function show(Request $request, string $id): WarehouseResource
    {
        abort_if(! $request->user()->tokenCan('inventory.read') && ! $request->user()->tokenCan('*'), 403);

        $warehouse = Warehouse::selectRaw('
            warehouses.*,
            (
                SELECT COUNT(*)
                FROM warehouse_spare_parts wsp
                WHERE wsp.warehouse_id = warehouses.id
                  AND wsp.current_stock > 0
            ) as items_count,
            (
                SELECT COALESCE(SUM(wsp.current_stock * wsp.average_unit_cost), 0)
                FROM warehouse_spare_parts wsp
                WHERE wsp.warehouse_id = warehouses.id
            ) as total_inventory_value,
            (
                SELECT COUNT(*)
                FROM warehouse_spare_parts wsp
                INNER JOIN spare_parts sp ON sp.id = wsp.spare_part_id
                WHERE wsp.warehouse_id = warehouses.id
                  AND sp.minimum_stock IS NOT NULL
                  AND wsp.current_stock < sp.minimum_stock
            ) as low_stock_count
        ')
            ->with([
                'stock' => fn ($q) => $q->with('sparePart')->orderByRaw('current_stock ASC'),
            ])
            ->findOrFail($id);

        return new WarehouseResource($warehouse);
    }
}
