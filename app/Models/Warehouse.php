<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'code',
    'name',
    'description',
    'location',
    'is_active',
    'created_by',
    'updated_by',
])]
class Warehouse extends BaseModel
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stock(): HasMany
    {
        return $this->hasMany(WarehouseSparePart::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class)->orderByDesc('performed_at');
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
