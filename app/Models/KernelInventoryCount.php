<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\KernelInventoryCountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/** Un corte de inventario: el palmiste (PKO) y la torta que había en bodega ese día (tn). */
#[Fillable([
    'tenant_id',
    'counted_on',
    'pko_tons',
    'cake_tons',
    'notes',
    'recorded_by',
])]
class KernelInventoryCount extends BaseModel
{
    /** @use HasFactory<KernelInventoryCountFactory> */
    use HasFactory;

    public function totalTons(): float
    {
        return round((float) ($this->pko_tons ?? 0) + (float) ($this->cake_tons ?? 0), 2);
    }

    protected function casts(): array
    {
        return [
            'counted_on' => 'date',
            'pko_tons' => 'decimal:2',
            'cake_tons' => 'decimal:2',
        ];
    }
}
