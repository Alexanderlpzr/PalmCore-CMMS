<?php

namespace App\Models;

use App\Domain\Inventory\Enums\SparePartAbcClassification;
use App\Domain\Inventory\Enums\SparePartCategoryType;
use App\Domain\Inventory\Enums\SparePartCriticality;
use App\Domain\Inventory\Enums\SparePartUnit;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\SparePartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'manufacturer_id',
    'supplier_id',
    'code',
    'name',
    'description',
    'category_type',
    'criticality',
    'abc_classification',
    'unit',
    'unit_cost',
    'minimum_stock',
    'maximum_stock',
    'reorder_point',
    'reorder_quantity',
    'lead_time_days',
    'notes',
    'is_active',
    'created_by',
    'updated_by',
])]
class SparePart extends BaseModel
{
    /** @use HasFactory<SparePartFactory> */
    use HasFactory;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Los equipos que llevan este repuesto («dónde se usa»). */
    public function equipmentUsage(): HasMany
    {
        return $this->hasMany(EquipmentSparePart::class);
    }

    public function warehouseStock(): HasMany
    {
        return $this->hasMany(WarehouseSparePart::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class)->orderByDesc('performed_at');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Total stock across all warehouses.
     * Uses the pre-loaded aggregate set by withSum('warehouseStock', 'current_stock')
     * to avoid N+1 queries when iterating a collection.
     */
    public function totalStock(): float
    {
        if (array_key_exists('warehouse_stock_sum_current_stock', $this->attributes)) {
            return (float) ($this->attributes['warehouse_stock_sum_current_stock'] ?? 0);
        }

        return (float) $this->warehouseStock()->withoutGlobalScopes()->sum('current_stock');
    }

    public function isBelowMinimumStock(): bool
    {
        if ($this->minimum_stock === null) {
            return false;
        }

        return $this->totalStock() < $this->minimum_stock;
    }

    public function isBelowReorderPoint(): bool
    {
        if ($this->reorder_point === null) {
            return false;
        }

        return $this->totalStock() < $this->reorder_point;
    }

    /**
     * Hay que pedirlo: bajo el punto de reorden o, si no tiene uno, en su mínimo o por
     * debajo —la misma regla que la tarjeta «Bajo el mínimo» del tablero de almacén—.
     */
    public function needsRestock(): bool
    {
        if ($this->reorder_point !== null) {
            return $this->isBelowReorderPoint();
        }

        return (float) $this->minimum_stock > 0 && $this->totalStock() <= (float) $this->minimum_stock;
    }

    /** El nivel con el que se pide: el punto de reorden, o el mínimo si no tiene. */
    public function restockThreshold(): ?float
    {
        $threshold = $this->reorder_point ?? $this->minimum_stock;

        return $threshold === null ? null : (float) $threshold;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'category_type' => SparePartCategoryType::class,
            'criticality' => SparePartCriticality::class,
            'abc_classification' => SparePartAbcClassification::class,
            'unit' => SparePartUnit::class,
            'unit_cost' => 'decimal:4',
            'minimum_stock' => 'decimal:4',
            'maximum_stock' => 'decimal:4',
            'reorder_point' => 'decimal:4',
            'reorder_quantity' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
