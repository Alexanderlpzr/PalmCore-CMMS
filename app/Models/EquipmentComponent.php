<?php

namespace App\Models;

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\EquipmentComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'equipment_id',
    'parent_id',
    'code',
    'name',
    'manufacturer',
    'model',
    'serial_number',
    'criticality',
    'useful_life_hours',
    'notes',
])]
class EquipmentComponent extends BaseModel
{
    /** @use HasFactory<EquipmentComponentFactory> */
    use HasFactory;

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(EquipmentComponent::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(EquipmentComponent::class, 'parent_id');
    }

    protected function casts(): array
    {
        return [
            'criticality' => EquipmentCriticality::class,
        ];
    }
}
