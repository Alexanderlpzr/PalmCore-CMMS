<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\EquipmentCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'parent_id',
    'code',
    'name',
    'description',
    'icon',
    'color',
    'sort_order',
    'is_active',
    'is_component_type',
])]
class EquipmentCategory extends BaseModel
{
    /** @use HasFactory<EquipmentCategoryFactory> */
    use HasFactory;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(EquipmentCategory::class, 'parent_id')
            ->orderBy('sort_order');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'category_id');
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_component_type' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
