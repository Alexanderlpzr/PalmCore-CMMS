<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\FruitReceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** La fruta que un proveedor entregó en un mes, según la báscula (tn). */
#[Fillable([
    'tenant_id',
    'fruit_supplier_id',
    'year',
    'month',
    'tons',
    'recorded_by',
])]
class FruitReception extends BaseModel
{
    /** @use HasFactory<FruitReceptionFactory> */
    use HasFactory;

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(FruitSupplier::class, 'fruit_supplier_id');
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'tons' => 'decimal:2',
        ];
    }
}
