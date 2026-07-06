<?php

namespace App\Models;

use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id',
    'po_number',
    'supplier_id',
    'warehouse_id',
    'status',
    'currency_code',
    'total',
    'expected_at',
    'ordered_at',
    'received_at',
    'notes',
    'created_by',
])]
class PurchaseOrder extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            PurchaseOrderStatus::Received->value,
            PurchaseOrderStatus::Cancelled->value,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Recompute the order total from its lines and persist it. */
    public function recalculateTotal(): void
    {
        $this->update(['total' => (float) $this->lines()->sum('line_total')]);
    }

    public function isFullyReceived(): bool
    {
        return $this->lines->every(fn (PurchaseOrderLine $line): bool => $line->isFullyReceived());
    }

    public function hasAnyReceipt(): bool
    {
        return $this->lines->contains(fn (PurchaseOrderLine $line): bool => (float) $line->quantity_received > 0.0);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'total' => 'decimal:2',
            'expected_at' => 'date',
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
