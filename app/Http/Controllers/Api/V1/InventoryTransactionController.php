<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Inventory\Services\InventoryService;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInventoryTransactionRequest;
use App\Http\Resources\Api\V1\InventoryTransactionResource;
use App\Models\SparePart;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;

class InventoryTransactionController extends Controller
{
    public function __construct(private readonly InventoryService $service) {}

    public function store(StoreInventoryTransactionRequest $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('inventory.write') && ! $request->user()->tokenCan('*'), 403);

        $data = $request->validated();

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $sparePart = SparePart::findOrFail($data['spare_part_id']);

        try {
            $transaction = match ($data['type']) {
                'entry' => $this->service->receiveEntry(
                    warehouse: $warehouse,
                    sparePart: $sparePart,
                    quantity: (float) $data['quantity'],
                    unitCost: (float) $data['unit_cost'],
                    performedBy: $request->user(),
                    referenceNumber: $data['reference_number'] ?? null,
                    notes: $data['notes'] ?? null,
                ),
                'exit' => $this->service->recordExit(
                    warehouse: $warehouse,
                    sparePart: $sparePart,
                    quantity: (float) $data['quantity'],
                    unitCost: (float) $data['unit_cost'],
                    performedBy: $request->user(),
                    referenceNumber: $data['reference_number'] ?? null,
                    notes: $data['notes'] ?? null,
                ),
            };
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw new BusinessRuleException($e->getMessage());
        }

        return (new InventoryTransactionResource($transaction))
            ->response()
            ->setStatusCode(201);
    }
}
