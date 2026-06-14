<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\InventoryTransactionType;
use App\Models\InventoryTransaction;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use App\Models\WorkOrder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    // ── Numbering ─────────────────────────────────────────────────────────────

    public function generateTransactionNumber(string $tenantId): string
    {
        $year = date('Y');

        $last = InventoryTransaction::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('transaction_number', 'like', "MVT-{$year}-%")
            ->lockForUpdate()
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $sequence = $last !== null ? (int) substr($last, -6) + 1 : 1;

        return sprintf('MVT-%s-%06d', $year, $sequence);
    }

    // ── Entry ─────────────────────────────────────────────────────────────────

    /**
     * Record a stock entry (purchase receipt, initial stock).
     */
    public function receiveEntry(
        Warehouse $warehouse,
        SparePart $sparePart,
        float $quantity,
        float $unitCost,
        User $performedBy,
        ?string $referenceNumber = null,
        ?string $notes = null,
    ): InventoryTransaction {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Entry quantity must be positive.');
        }

        return DB::transaction(function () use (
            $warehouse, $sparePart, $quantity, $unitCost, $performedBy, $referenceNumber, $notes
        ): InventoryTransaction {
            $wsp = $this->lockWsp($warehouse, $sparePart);

            $previousStock = (float) $wsp->current_stock;
            $newStock = $previousStock + $quantity;
            $newAverageCost = $this->calculateAverageCost(
                $previousStock, (float) $wsp->average_unit_cost, $quantity, $unitCost
            );

            $wsp->update([
                'current_stock' => $newStock,
                'average_unit_cost' => $newAverageCost,
            ]);

            return $this->createTransaction($wsp, [
                'type' => InventoryTransactionType::Entry,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'performed_by' => $performedBy->id,
            ]);
        });
    }

    // ── Exit ──────────────────────────────────────────────────────────────────

    /**
     * Record a manual stock exit (disposal, damage, supplier return).
     */
    public function recordExit(
        Warehouse $warehouse,
        SparePart $sparePart,
        float $quantity,
        float $unitCost,
        User $performedBy,
        ?string $referenceNumber = null,
        ?string $notes = null,
    ): InventoryTransaction {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Exit quantity must be positive.');
        }

        return DB::transaction(function () use (
            $warehouse, $sparePart, $quantity, $unitCost, $performedBy, $referenceNumber, $notes
        ): InventoryTransaction {
            $wsp = $this->lockWsp($warehouse, $sparePart);

            $available = (float) $wsp->current_stock - (float) $wsp->reserved_stock;
            if ($available < $quantity) {
                throw new \RuntimeException(
                    "Insufficient available stock. Available: {$available}, requested: {$quantity}."
                );
            }

            $previousStock = (float) $wsp->current_stock;
            $newStock = $previousStock - $quantity;

            $wsp->update(['current_stock' => $newStock]);

            return $this->createTransaction($wsp, [
                'type' => InventoryTransactionType::Exit,
                'quantity' => -$quantity,
                'unit_cost' => $unitCost,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'performed_by' => $performedBy->id,
            ]);
        });
    }

    // ── Adjustment ────────────────────────────────────────────────────────────

    /**
     * Adjust stock to a new absolute count (physical inventory count).
     * The quantity stored in the transaction is the signed delta (positive = surplus, negative = shrinkage).
     */
    public function adjustStock(
        Warehouse $warehouse,
        SparePart $sparePart,
        float $newAbsoluteStock,
        float $unitCost,
        User $performedBy,
        ?string $notes = null,
    ): InventoryTransaction {
        if ($newAbsoluteStock < 0) {
            throw new \InvalidArgumentException('Adjusted stock cannot be negative.');
        }

        return DB::transaction(function () use (
            $warehouse, $sparePart, $newAbsoluteStock, $unitCost, $performedBy, $notes
        ): InventoryTransaction {
            $wsp = $this->lockWsp($warehouse, $sparePart);

            $previousStock = (float) $wsp->current_stock;
            $delta = $newAbsoluteStock - $previousStock;

            $wsp->update(['current_stock' => $newAbsoluteStock]);

            return $this->createTransaction($wsp, [
                'type' => InventoryTransactionType::Adjustment,
                'quantity' => $delta,
                'unit_cost' => $unitCost,
                'previous_stock' => $previousStock,
                'new_stock' => $newAbsoluteStock,
                'notes' => $notes,
                'performed_by' => $performedBy->id,
            ]);
        });
    }

    // ── Transfer ──────────────────────────────────────────────────────────────

    /**
     * Transfer stock between warehouses.
     * Creates a pair of linked transactions (transfer_out + transfer_in) atomically.
     *
     * @return array{out: InventoryTransaction, in: InventoryTransaction}
     */
    public function transferStock(
        Warehouse $sourceWarehouse,
        Warehouse $destinationWarehouse,
        SparePart $sparePart,
        float $quantity,
        float $unitCost,
        User $performedBy,
        ?string $notes = null,
    ): array {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Transfer quantity must be positive.');
        }

        if ($sourceWarehouse->id === $destinationWarehouse->id) {
            throw new \InvalidArgumentException('Source and destination warehouses must differ.');
        }

        return DB::transaction(function () use (
            $sourceWarehouse, $destinationWarehouse, $sparePart, $quantity, $unitCost, $performedBy, $notes
        ): array {
            // Lock in consistent order (by UUID) to prevent deadlocks
            [$firstId, $secondId] = $sourceWarehouse->id < $destinationWarehouse->id
                ? [$sourceWarehouse->id, $destinationWarehouse->id]
                : [$destinationWarehouse->id, $sourceWarehouse->id];

            $sourceWsp = $this->lockWsp($sourceWarehouse, $sparePart);
            $destWsp = $this->lockWsp($destinationWarehouse, $sparePart);

            // Reload in canonical lock order to prevent deadlocks
            $firstWsp = $firstId === $sourceWarehouse->id ? $sourceWsp : $destWsp;
            $secondWsp = $firstId === $sourceWarehouse->id ? $destWsp : $sourceWsp;

            $firstWsp = $this->relock($firstWsp);
            $secondWsp = $this->relock($secondWsp);

            $sourceWsp = $firstId === $sourceWarehouse->id ? $firstWsp : $secondWsp;
            $destWsp = $firstId === $sourceWarehouse->id ? $secondWsp : $firstWsp;

            $available = (float) $sourceWsp->current_stock - (float) $sourceWsp->reserved_stock;
            if ($available < $quantity) {
                throw new \RuntimeException(
                    "Insufficient available stock in source warehouse. Available: {$available}, requested: {$quantity}."
                );
            }

            $sourcePrev = (float) $sourceWsp->current_stock;
            $destPrev = (float) $destWsp->current_stock;

            $sourceWsp->update(['current_stock' => $sourcePrev - $quantity]);
            $destWsp->update([
                'current_stock' => $destPrev + $quantity,
                'average_unit_cost' => $this->calculateAverageCost(
                    $destPrev, (float) $destWsp->average_unit_cost, $quantity, $unitCost
                ),
            ]);

            $sharedData = [
                'unit_cost' => $unitCost,
                'source_warehouse_id' => $sourceWarehouse->id,
                'destination_warehouse_id' => $destinationWarehouse->id,
                'notes' => $notes,
                'performed_by' => $performedBy->id,
            ];

            $outTx = $this->createTransaction($sourceWsp, array_merge($sharedData, [
                'type' => InventoryTransactionType::TransferOut,
                'quantity' => -$quantity,
                'previous_stock' => $sourcePrev,
                'new_stock' => $sourcePrev - $quantity,
            ]));

            $inTx = $this->createTransaction($destWsp, array_merge($sharedData, [
                'type' => InventoryTransactionType::TransferIn,
                'quantity' => $quantity,
                'previous_stock' => $destPrev,
                'new_stock' => $destPrev + $quantity,
                'related_transaction_id' => $outTx->id,
            ]));

            $outTx->update(['related_transaction_id' => $inTx->id]);

            return ['out' => $outTx->refresh(), 'in' => $inTx];
        });
    }

    // ── Consumption (Work Order) ───────────────────────────────────────────────

    /**
     * Record consumption of a spare part by a work order.
     * Decrements current_stock and reserved_stock simultaneously.
     */
    public function consumeFromWorkOrder(
        Warehouse $warehouse,
        SparePart $sparePart,
        float $quantity,
        float $unitCost,
        WorkOrder $workOrder,
        User $performedBy,
        ?string $workOrderPartId = null,
        ?string $notes = null,
    ): InventoryTransaction {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Consumption quantity must be positive.');
        }

        return DB::transaction(function () use (
            $warehouse, $sparePart, $quantity, $unitCost, $workOrder, $performedBy, $workOrderPartId, $notes
        ): InventoryTransaction {
            $wsp = $this->lockWsp($warehouse, $sparePart);

            if ((float) $wsp->current_stock < $quantity) {
                throw new \RuntimeException(
                    "Insufficient stock for consumption. Stock: {$wsp->current_stock}, requested: {$quantity}."
                );
            }

            if ((float) $wsp->reserved_stock < $quantity) {
                throw new \RuntimeException(
                    "Cannot consume more than reserved. Reserved: {$wsp->reserved_stock}, requested: {$quantity}."
                );
            }

            $previousStock = (float) $wsp->current_stock;
            $newStock = $previousStock - $quantity;
            $newReserved = (float) $wsp->reserved_stock - $quantity;

            $wsp->update([
                'current_stock' => $newStock,
                'reserved_stock' => $newReserved,
            ]);

            return $this->createTransaction($wsp, [
                'type' => InventoryTransactionType::Consumption,
                'quantity' => -$quantity,
                'unit_cost' => $unitCost,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'work_order_id' => $workOrder->id,
                'work_order_part_id' => $workOrderPartId,
                'reference_number' => $workOrder->work_order_number,
                'notes' => $notes,
                'performed_by' => $performedBy->id,
            ]);
        });
    }

    // ── Return (Work Order) ───────────────────────────────────────────────────

    /**
     * Return unused parts from a work order back to the warehouse.
     */
    public function returnFromWorkOrder(
        Warehouse $warehouse,
        SparePart $sparePart,
        float $quantity,
        float $unitCost,
        WorkOrder $workOrder,
        User $performedBy,
        ?string $workOrderPartId = null,
        ?string $notes = null,
    ): InventoryTransaction {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Return quantity must be positive.');
        }

        return DB::transaction(function () use (
            $warehouse, $sparePart, $quantity, $unitCost, $workOrder, $performedBy, $workOrderPartId, $notes
        ): InventoryTransaction {
            $wsp = $this->lockWsp($warehouse, $sparePart);

            $previousStock = (float) $wsp->current_stock;
            $newStock = $previousStock + $quantity;
            $newAverageCost = $this->calculateAverageCost(
                $previousStock, (float) $wsp->average_unit_cost, $quantity, $unitCost
            );

            $wsp->update([
                'current_stock' => $newStock,
                'average_unit_cost' => $newAverageCost,
            ]);

            return $this->createTransaction($wsp, [
                'type' => InventoryTransactionType::Return,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'work_order_id' => $workOrder->id,
                'work_order_part_id' => $workOrderPartId,
                'reference_number' => $workOrder->work_order_number,
                'notes' => $notes,
                'performed_by' => $performedBy->id,
            ]);
        });
    }

    // ── Reservation ───────────────────────────────────────────────────────────

    /**
     * Reserve stock for a work order (does NOT create a transaction).
     * Call when parts are added to a planned/in-progress WO.
     */
    public function reserveForWorkOrder(
        Warehouse $warehouse,
        SparePart $sparePart,
        float $quantity,
    ): WarehouseSparePart {
        return DB::transaction(function () use ($warehouse, $sparePart, $quantity): WarehouseSparePart {
            $wsp = $this->lockWsp($warehouse, $sparePart);

            $available = (float) $wsp->current_stock - (float) $wsp->reserved_stock;
            if ($available < $quantity) {
                throw new \RuntimeException(
                    "Cannot reserve {$quantity} units. Available: {$available}."
                );
            }

            $wsp->update(['reserved_stock' => (float) $wsp->reserved_stock + $quantity]);

            return $wsp->refresh();
        });
    }

    /**
     * Release a stock reservation (WO cancelled or parts removed from WO).
     * Does NOT create a transaction.
     */
    public function releaseReservation(
        Warehouse $warehouse,
        SparePart $sparePart,
        float $quantity,
    ): WarehouseSparePart {
        return DB::transaction(function () use ($warehouse, $sparePart, $quantity): WarehouseSparePart {
            $wsp = $this->lockWsp($warehouse, $sparePart);

            $wsp->update(['reserved_stock' => max(0.0, (float) $wsp->reserved_stock - $quantity)]);

            return $wsp->refresh();
        });
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    /**
     * Ensure a WarehouseSparePart row exists, then re-fetch it with a row-level lock.
     * Must be called inside a DB::transaction().
     */
    private function lockWsp(Warehouse $warehouse, SparePart $sparePart): WarehouseSparePart
    {
        try {
            $wsp = WarehouseSparePart::firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'spare_part_id' => $sparePart->id],
                [
                    'tenant_id' => $warehouse->tenant_id,
                    'current_stock' => 0,
                    'reserved_stock' => 0,
                    'average_unit_cost' => $sparePart->unit_cost,
                ]
            );
        } catch (UniqueConstraintViolationException) {
            // Two concurrent requests raced on firstOrCreate — re-fetch the winner's row.
            $wsp = WarehouseSparePart::withoutGlobalScopes()
                ->where('warehouse_id', $warehouse->id)
                ->where('spare_part_id', $sparePart->id)
                ->firstOrFail();
        }

        // Re-fetch with SELECT FOR UPDATE to serialize concurrent access
        return WarehouseSparePart::withoutGlobalScopes()
            ->where('id', $wsp->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function relock(WarehouseSparePart $wsp): WarehouseSparePart
    {
        return WarehouseSparePart::withoutGlobalScopes()
            ->where('id', $wsp->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function createTransaction(WarehouseSparePart $wsp, array $data): InventoryTransaction
    {
        $sparePart = $wsp->sparePart;
        $quantity = (float) $data['quantity'];
        $unitCost = (float) $data['unit_cost'];

        return InventoryTransaction::create([
            ...$data,
            'tenant_id' => $wsp->tenant_id,
            'warehouse_id' => $wsp->warehouse_id,
            'spare_part_id' => $wsp->spare_part_id,
            'warehouse_spare_part_id' => $wsp->id,
            'transaction_number' => $this->generateTransactionNumber($wsp->tenant_id),
            'total_cost' => $quantity !== 0.0 ? round(abs($quantity) * $unitCost, 4) : null,
            'spare_part_code_snapshot' => $sparePart->code,
            'spare_part_name_snapshot' => $sparePart->name,
            'performed_at' => $data['performed_at'] ?? now(),
        ]);
    }

    /**
     * Weighted moving average cost.
     * Returns incoming cost when there is no existing stock.
     */
    private function calculateAverageCost(
        float $existingQty,
        ?float $existingAvgCost,
        float $incomingQty,
        float $incomingCost,
    ): float {
        $existingAvgCost ??= 0.0;
        $totalQty = $existingQty + $incomingQty;

        if ($totalQty <= 0) {
            return $incomingCost;
        }

        return round(
            (($existingQty * $existingAvgCost) + ($incomingQty * $incomingCost)) / $totalQty,
            4
        );
    }
}
