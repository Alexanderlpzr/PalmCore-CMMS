<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    // ── Numbering ─────────────────────────────────────────────────────────────

    /**
     * Generate OC-{YEAR}-{SEQUENTIAL}. Sequential is 6-digit, per tenant per year.
     * lockForUpdate() prevents duplicate numbers under concurrency.
     */
    public function generateNumber(string $tenantId): string
    {
        $year = date('Y');

        $last = PurchaseOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('po_number', 'like', "OC-{$year}-%")
            ->lockForUpdate()
            ->orderByRaw('CAST(RIGHT(po_number, 6) AS INTEGER) DESC')
            ->value('po_number');

        $sequence = $last !== null ? ((int) substr($last, -6) + 1) : 1;

        return sprintf('OC-%s-%06d', $year, $sequence);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    /**
     * Create a draft purchase order with its lines.
     *
     * @param  array<string, mixed>  $data  tenant_id, warehouse_id, supplier_id?, expected_at?, notes?
     * @param  array<int, array{spare_part_id: string, quantity_ordered: float, unit_cost: float}>  $lines
     */
    public function create(array $data, array $lines, User $createdBy): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $lines, $createdBy): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::create([
                ...$data,
                'po_number' => $this->generateNumber($data['tenant_id']),
                'status' => PurchaseOrderStatus::Draft->value,
                'created_by' => $createdBy->id,
            ]);

            foreach ($lines as $line) {
                $this->addLine($purchaseOrder, $line);
            }

            $purchaseOrder->recalculateTotal();

            return $purchaseOrder->refresh();
        });
    }

    /**
     * @param  array{spare_part_id: string, quantity_ordered: float, unit_cost: float}  $data
     */
    public function addLine(PurchaseOrder $purchaseOrder, array $data): PurchaseOrderLine
    {
        $quantity = (float) $data['quantity_ordered'];
        $unitCost = (float) $data['unit_cost'];

        return $purchaseOrder->lines()->create([
            'tenant_id' => $purchaseOrder->tenant_id,
            'spare_part_id' => $data['spare_part_id'],
            'quantity_ordered' => $quantity,
            'unit_cost' => $unitCost,
            'line_total' => round($quantity * $unitCost, 2),
        ]);
    }

    // ── Transitions ─────────────────────────────────────────────────────────────

    /** Send a draft order to the supplier. */
    public function send(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            throw new \RuntimeException('Solo una orden en borrador puede enviarse.');
        }

        if ($purchaseOrder->lines()->doesntExist()) {
            throw new \RuntimeException('No se puede enviar una orden de compra sin renglones.');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrderStatus::Sent->value,
            'ordered_at' => now(),
        ]);

        return $purchaseOrder->refresh();
    }

    public function cancel(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->status->isTerminal()) {
            throw new \RuntimeException('Esta orden de compra ya está cerrada.');
        }

        $purchaseOrder->update(['status' => PurchaseOrderStatus::Cancelled->value]);

        return $purchaseOrder->refresh();
    }

    // ── Receiving ───────────────────────────────────────────────────────────────

    /**
     * Receive a quantity against a line: books the stock into the PO's warehouse
     * (Entry transaction + average-cost update via InventoryService) and advances
     * the order status. Quantity is clamped to what is still pending on the line.
     */
    public function receiveLine(PurchaseOrderLine $line, float $quantity, User $performedBy): PurchaseOrderLine
    {
        $line->loadMissing(['purchaseOrder.warehouse', 'sparePart']);
        $purchaseOrder = $line->purchaseOrder;

        if (! $purchaseOrder->status->canReceive()) {
            throw new \RuntimeException('Solo se puede recibir contra una orden enviada.');
        }

        $quantity = min($quantity, $line->pendingQuantity());

        if ($quantity <= 0) {
            throw new \RuntimeException('No hay cantidad pendiente por recibir en este renglón.');
        }

        DB::transaction(function () use ($line, $purchaseOrder, $quantity, $performedBy): void {
            $this->inventoryService->receiveEntry(
                $purchaseOrder->warehouse,
                $line->sparePart,
                $quantity,
                (float) $line->unit_cost,
                $performedBy,
                referenceNumber: $purchaseOrder->po_number,
                notes: "Recepción OC {$purchaseOrder->po_number}",
            );

            $line->update([
                'quantity_received' => (float) $line->quantity_received + $quantity,
            ]);

            $this->refreshReceiptStatus($purchaseOrder->refresh());
        });

        return $line->refresh();
    }

    /** Receive every pending quantity on the order in one action. */
    public function receiveAll(PurchaseOrder $purchaseOrder, User $performedBy): PurchaseOrder
    {
        foreach ($purchaseOrder->lines()->get() as $line) {
            if ($line->pendingQuantity() > 0) {
                $this->receiveLine($line, $line->pendingQuantity(), $performedBy);
            }
        }

        return $purchaseOrder->refresh();
    }

    private function refreshReceiptStatus(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->load('lines');

        if ($purchaseOrder->isFullyReceived()) {
            $purchaseOrder->update([
                'status' => PurchaseOrderStatus::Received->value,
                'received_at' => now(),
            ]);
        } elseif ($purchaseOrder->hasAnyReceipt()) {
            $purchaseOrder->update(['status' => PurchaseOrderStatus::PartiallyReceived->value]);
        }
    }

    // ── Reorder assistant ─────────────────────────────────────────────────────

    /**
     * Create one draft purchase order per supplier for every active spare part
     * currently below its reorder point, ordering its reorder_quantity (or the
     * shortfall). Parts with no supplier are grouped under a supplier-less PO.
     * Returns the created orders. No-op (empty) when nothing needs reordering or
     * the tenant has no active warehouse to receive into.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function generateFromReorder(string $tenantId, User $createdBy): Collection
    {
        $warehouse = Warehouse::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if ($warehouse === null) {
            return collect();
        }

        $toReorder = SparePart::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('reorder_point')
            ->withSum('warehouseStock', 'current_stock')
            ->get()
            ->filter(fn (SparePart $part): bool => $part->isBelowReorderPoint());

        $created = collect();

        foreach ($toReorder->groupBy('supplier_id') as $supplierId => $parts) {
            $lines = $parts->map(fn (SparePart $part): array => [
                'spare_part_id' => $part->id,
                'quantity_ordered' => $this->reorderQuantityFor($part),
                'unit_cost' => (float) ($part->unit_cost ?? 0),
            ])->all();

            $created->push($this->create([
                'tenant_id' => $tenantId,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplierId ?: null,
                'notes' => 'Generada automáticamente desde puntos de reorden.',
            ], $lines, $createdBy));
        }

        return $created;
    }

    private function reorderQuantityFor(SparePart $part): float
    {
        if ($part->reorder_quantity !== null && (float) $part->reorder_quantity > 0) {
            return (float) $part->reorder_quantity;
        }

        // Fallback: bring stock back up to the reorder point.
        $shortfall = (float) $part->reorder_point - $part->totalStock();

        return max(1.0, round($shortfall, 4));
    }
}
