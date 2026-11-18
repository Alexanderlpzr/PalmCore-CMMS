<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** La fruta que un proveedor entregará en un mes del plan (tn). */
#[Fillable([
    'tenant_id',
    'annual_plan_id',
    'fruit_supplier_id',
    'month',
    'tons',
])]
class AnnualPlanFruit extends BaseModel
{
    protected $table = 'annual_plan_fruit';

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(FruitSupplier::class, 'fruit_supplier_id');
    }

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'tons' => 'decimal:2',
        ];
    }
}
