<?php

namespace App\Models;

use App\Domain\Inventory\Enums\InventoryTransactionType;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\InventoryTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id',
    'warehouse_id',
    'spare_part_id',
    'warehouse_spare_part_id',
    'related_transaction_id',
    'source_warehouse_id',
    'destination_warehouse_id',
    'work_order_id',
    'work_order_part_id',
    'transaction_number',
    'type',
    'quantity',
    'unit_cost',
    'total_cost',
    'previous_stock',
    'new_stock',
    'spare_part_code_snapshot',
    'spare_part_name_snapshot',
    'reference_number',
    'notes',
    'performed_by',
    'performed_at',
])]
class InventoryTransaction extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<InventoryTransactionFactory> */
    use HasFactory;

    use HasUuids;

    // No soft deletes — transactions are immutable ledger entries; reversals create new records

    // ── Relationships ─────────────────────────────────────────────────────────

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function warehouseSparePart(): BelongsTo
    {
        return $this->belongsTo(WarehouseSparePart::class);
    }

    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'related_transaction_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(InventoryTransaction::class, 'related_transaction_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'type' => InventoryTransactionType::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'previous_stock' => 'decimal:4',
            'new_stock' => 'decimal:4',
            'performed_at' => 'datetime',
        ];
    }
}
