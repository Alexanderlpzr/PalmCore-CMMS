<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Maintenance\Enums\WorkOrderPartStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use Illuminate\Database\Eloquent\Collection;

class WorkOrderInventoryService
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    // ── Reserve (OT → Planned) ────────────────────────────────────────────────

    /**
     * Reserve stock for all inventory-linked parts in `requested` status.
     * Called when an OT transitions to Planned.
     */
    public function reservePartsForWorkOrder(WorkOrder $workOrder, User $actor): void
    {
        foreach ($this->linkedParts($workOrder, [WorkOrderPartStatus::Requested]) as $part) {
            $this->guardUnitCostSnapshot($part);

            $this->inventoryService->reserveForWorkOrder(
                $part->warehouse,
                $part->sparePart,
                (float) $part->quantity,
            );

            $part->update([
                'status' => WorkOrderPartStatus::Reserved->value,
                'reserved_quantity' => $part->quantity,
            ]);
        }
    }

    // ── Consume (OT → Completed) ──────────────────────────────────────────────

    /**
     * Consume all inventory-linked parts that are reserved (or still requested, for Emergency WOs).
     * Called when an OT transitions to Completed.
     */
    public function consumePartsForWorkOrder(WorkOrder $workOrder, User $actor): void
    {
        // Auto-reserve requested parts first (Emergency WOs skip Planned)
        $requestedParts = $this->linkedParts($workOrder, [WorkOrderPartStatus::Requested]);

        foreach ($requestedParts as $part) {
            $this->guardUnitCostSnapshot($part);

            $this->inventoryService->reserveForWorkOrder(
                $part->warehouse,
                $part->sparePart,
                (float) $part->quantity,
            );

            $part->update([
                'status' => WorkOrderPartStatus::Reserved->value,
                'reserved_quantity' => $part->quantity,
            ]);

            $part->refresh();
        }

        // Consume all reserved parts
        foreach ($this->linkedParts($workOrder, [WorkOrderPartStatus::Reserved]) as $part) {
            $this->inventoryService->consumeFromWorkOrder(
                $part->warehouse,
                $part->sparePart,
                (float) $part->reserved_quantity,
                (float) $part->unit_cost_snapshot,
                $workOrder,
                $actor,
                $part->id,
            );

            $part->update([
                'status' => WorkOrderPartStatus::Issued->value,
                'issued_quantity' => $part->reserved_quantity,
                'reserved_quantity' => 0,
            ]);
        }
    }

    // ── Release (OT → Cancelled) ──────────────────────────────────────────────

    /**
     * Release all reservations for an OT being cancelled.
     * Requested parts are simply marked cancelled. Reserved parts have their reservation released.
     */
    public function releasePartsForWorkOrder(WorkOrder $workOrder, User $actor): void
    {
        foreach ($this->linkedParts($workOrder, [WorkOrderPartStatus::Requested]) as $part) {
            $part->update([
                'status' => WorkOrderPartStatus::Cancelled->value,
            ]);
        }

        foreach ($this->linkedParts($workOrder, [WorkOrderPartStatus::Reserved]) as $part) {
            $this->inventoryService->releaseReservation(
                $part->warehouse,
                $part->sparePart,
                (float) $part->reserved_quantity,
            );

            $part->update([
                'status' => WorkOrderPartStatus::Cancelled->value,
                'reserved_quantity' => 0,
            ]);
        }
    }

    // ── Undo Consumption (OT Completed → InProgress via supervisor rejection) ─

    /**
     * Reverse consumption when a supervisor rejects completion (Completed → InProgress).
     * Returns parts to warehouse stock + re-reserves them so the WO can continue.
     */
    public function undoConsumptionForWorkOrder(WorkOrder $workOrder, User $actor): void
    {
        foreach ($this->linkedParts($workOrder, [WorkOrderPartStatus::Issued]) as $part) {
            // Return stock to warehouse (creates a Return transaction for the audit trail)
            $this->inventoryService->returnFromWorkOrder(
                $part->warehouse,
                $part->sparePart,
                (float) $part->issued_quantity,
                (float) $part->unit_cost_snapshot,
                $workOrder,
                $actor,
                $part->id,
                'Devolución automática por rechazo de completado — OT vuelve a ejecución',
            );

            // Re-establish the reservation so the WO can be completed again
            $this->inventoryService->reserveForWorkOrder(
                $part->warehouse,
                $part->sparePart,
                (float) $part->issued_quantity,
            );

            $part->update([
                'status' => WorkOrderPartStatus::Reserved->value,
                'reserved_quantity' => $part->issued_quantity,
                'issued_quantity' => 0,
            ]);
        }
    }

    // ── Return Part (partial return after issuance) ───────────────────────────

    /**
     * Return a quantity of an issued part back to the warehouse.
     * Validates that returned_quantity does not exceed issued_quantity.
     */
    public function returnPartFromWorkOrder(
        WorkOrderPart $part,
        float $returnQuantity,
        User $actor,
    ): void {
        if ($part->status !== WorkOrderPartStatus::Issued) {
            throw new \RuntimeException(
                "Only issued parts can be returned. Current status: {$part->status->label()}."
            );
        }

        if (! $part->hasInventoryLink()) {
            throw new \RuntimeException('Part has no inventory link (spare_part_id / warehouse_id missing).');
        }

        $alreadyReturned = (float) $part->returned_quantity;
        $issued = (float) $part->issued_quantity;

        if ($alreadyReturned + $returnQuantity > $issued) {
            throw new \RuntimeException(
                "Cannot return {$returnQuantity}. Already returned: {$alreadyReturned}, issued: {$issued}."
            );
        }

        $this->inventoryService->returnFromWorkOrder(
            $part->warehouse,
            $part->sparePart,
            $returnQuantity,
            (float) $part->unit_cost_snapshot,
            $part->workOrder,
            $actor,
            $part->id,
        );

        $newReturned = $alreadyReturned + $returnQuantity;
        $fullyReturned = $newReturned >= $issued;

        $part->update([
            'returned_quantity' => $newReturned,
            'status' => $fullyReturned
                ? WorkOrderPartStatus::Returned->value
                : WorkOrderPartStatus::Issued->value,
        ]);
    }

    // ── Cancel single part (releases reservation, use before soft-delete) ─────

    /**
     * Cancel a single part, releasing its reservation if it is in reserved status.
     * Call this before soft-deleting a WorkOrderPart to avoid orphaned reserved_stock.
     */
    public function cancelPart(WorkOrderPart $part, User $actor): void
    {
        if (! $part->hasInventoryLink()) {
            return;
        }

        if ($part->status === WorkOrderPartStatus::Reserved) {
            $this->inventoryService->releaseReservation(
                $part->warehouse,
                $part->sparePart,
                (float) $part->reserved_quantity,
            );
        }

        $part->update([
            'status' => WorkOrderPartStatus::Cancelled->value,
            'reserved_quantity' => 0,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * @param  WorkOrderPartStatus[]  $statuses
     * @return Collection<int, WorkOrderPart>
     */
    private function linkedParts(WorkOrder $workOrder, array $statuses): Collection
    {
        return $workOrder->parts()
            ->whereNotNull('spare_part_id')
            ->whereNotNull('warehouse_id')
            ->whereIn('status', array_map(fn (WorkOrderPartStatus $s) => $s->value, $statuses))
            ->with(['sparePart', 'warehouse'])
            ->get();
    }

    private function guardUnitCostSnapshot(WorkOrderPart $part): void
    {
        if ($part->unit_cost_snapshot === null) {
            throw new \RuntimeException(
                "WorkOrderPart [{$part->id}] is missing unit_cost_snapshot. Cannot reserve or consume without a cost snapshot."
            );
        }
    }
}
