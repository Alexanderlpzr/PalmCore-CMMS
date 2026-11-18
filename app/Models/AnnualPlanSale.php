<?php

namespace App\Models;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lo que se planea venderle a un cliente en un mes (tn). Sin cliente, «Por concretar». */
#[Fillable([
    'tenant_id',
    'annual_plan_id',
    'customer_id',
    'product',
    'month',
    'tons',
])]
class AnnualPlanSale extends BaseModel
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected function casts(): array
    {
        return [
            'product' => CommercialProduct::class,
            'month' => 'integer',
            'tons' => 'decimal:2',
        ];
    }
}
