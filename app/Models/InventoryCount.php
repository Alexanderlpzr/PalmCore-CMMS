<?php

namespace App\Models;

use App\Domain\Inventory\Enums\InventoryCountScope;
use App\Domain\Inventory\Enums\InventoryCountStatus;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\InventoryCountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una jornada de conteo de un almacén: lo que el sistema decía al empezar y lo que se
 * encontró en el estante, repuesto por repuesto.
 */
#[Fillable([
    'tenant_id',
    'warehouse_id',
    'count_number',
    'status',
    'scope',
    'location_filter',
    'notes',
    'created_by',
    'applied_by',
    'applied_at',
])]
class InventoryCount extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<InventoryCountFactory> */
    use HasFactory;

    use HasUuids;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCountLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === InventoryCountStatus::Open;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'status' => InventoryCountStatus::class,
            'scope' => InventoryCountScope::class,
            'applied_at' => 'datetime',
        ];
    }
}
