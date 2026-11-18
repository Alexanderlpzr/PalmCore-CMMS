<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\OilTankFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un tanque de aceite crudo de la planta: «Tanque 1», «Tanque 2»… */
#[Fillable([
    'tenant_id',
    'name',
    'capacity_tons',
    'sort_order',
    'is_active',
])]
class OilTank extends BaseModel
{
    /** @use HasFactory<OilTankFactory> */
    use HasFactory;

    public function readings(): HasMany
    {
        return $this->hasMany(OilTankReading::class);
    }

    protected function casts(): array
    {
        return [
            'capacity_tons' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
