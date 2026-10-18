<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un repuesto dentro de un conteo: lo esperado al empezar y lo que se contó. */
#[Fillable([
    'tenant_id',
    'inventory_count_id',
    'spare_part_id',
    'bin_location',
    'expected_quantity',
    'counted_quantity',
    'counted_by',
    'counted_at',
])]
class InventoryCountLine extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    /** Contado menos esperado; null mientras no se ha contado. */
    public function difference(): ?float
    {
        return $this->counted_quantity === null ? null : (float) $this->counted_quantity - (float) $this->expected_quantity;
    }

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:4',
            'counted_quantity' => 'decimal:4',
            'counted_at' => 'datetime',
        ];
    }
}
