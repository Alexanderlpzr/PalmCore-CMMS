<?php

namespace App\Domain\Inventory\Services;

use App\Models\SparePart;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    public function create(array $data, User $createdBy): Warehouse
    {
        return DB::transaction(function () use ($data, $createdBy): Warehouse {
            return Warehouse::create([
                ...$data,
                'created_by' => $createdBy->id,
                'is_active' => true,
            ]);
        });
    }

    public function update(Warehouse $warehouse, array $data, User $updatedBy): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $data, $updatedBy): Warehouse {
            $warehouse->update([
                ...$data,
                'updated_by' => $updatedBy->id,
            ]);

            return $warehouse->refresh();
        });
    }

    public function deactivate(Warehouse $warehouse, User $updatedBy): Warehouse
    {
        $warehouse->update(['is_active' => false, 'updated_by' => $updatedBy->id]);

        return $warehouse;
    }

    /**
     * Register a spare part in a warehouse with an optional initial stock and bin location.
     * Uses firstOrCreate to be idempotent — safe to call multiple times.
     */
    public function registerSparePart(
        Warehouse $warehouse,
        SparePart $sparePart,
        ?string $binLocation = null,
    ): WarehouseSparePart {
        return WarehouseSparePart::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'spare_part_id' => $sparePart->id],
            [
                'tenant_id' => $warehouse->tenant_id,
                'current_stock' => 0,
                'reserved_stock' => 0,
                'average_unit_cost' => $sparePart->unit_cost,
                'bin_location' => $binLocation,
            ]
        );
    }
}
