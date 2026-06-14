<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\WarehouseSparePartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'warehouse_id',
    'spare_part_id',
    'current_stock',
    'reserved_stock',
    'average_unit_cost',
    'bin_location',
    'last_counted_by',
    'last_counted_at',
])]
class WarehouseSparePart extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<WarehouseSparePartFactory> */
    use HasFactory;

    use HasUuids;

    // No soft deletes — retiring a spare part from a warehouse means adjusting stock to 0

    // ── Relationships ─────────────────────────────────────────────────────────

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function lastCountedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_counted_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class)->orderByDesc('performed_at');
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getAvailableStockAttribute(): float
    {
        return (float) $this->current_stock - (float) $this->reserved_stock;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:4',
            'reserved_stock' => 'decimal:4',
            'average_unit_cost' => 'decimal:4',
            'last_counted_at' => 'datetime',
        ];
    }
}
