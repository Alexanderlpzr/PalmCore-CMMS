<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Los parámetros de la planta en un mes del plan: cuánta fruta cabe, la TEA esperada y
 * las tasas de almendra (sobre la fruta) y de PKO (sobre la almendra).
 */
#[Fillable([
    'tenant_id',
    'annual_plan_id',
    'month',
    'plant_capacity_tons',
    'tea_percent',
    'kernel_percent',
    'pko_percent',
])]
class AnnualPlanMonth extends BaseModel
{
    public function plan(): BelongsTo
    {
        return $this->belongsTo(AnnualPlan::class, 'annual_plan_id');
    }

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'plant_capacity_tons' => 'decimal:2',
            'tea_percent' => 'decimal:2',
            'kernel_percent' => 'decimal:2',
            'pko_percent' => 'decimal:2',
        ];
    }
}
