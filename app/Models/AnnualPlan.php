<?php

namespace App\Models;

use App\Domain\Commercial\Enums\PlanVersion;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\AnnualPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El plan comercial de un año en una de sus dos versiones ({@see PlanVersion}): la fruta
 * por proveedor, los parámetros de la planta y las ventas por cliente, mes a mes.
 */
#[Fillable([
    'tenant_id',
    'year',
    'version',
    'opening_oil_tons',
    'approved_at',
    'approved_by',
    'created_by',
    'notes',
])]
class AnnualPlan extends BaseModel
{
    /** @use HasFactory<AnnualPlanFactory> */
    use HasFactory;

    public function months(): HasMany
    {
        return $this->hasMany(AnnualPlanMonth::class);
    }

    public function fruit(): HasMany
    {
        return $this->hasMany(AnnualPlanFruit::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(AnnualPlanSale::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** El original aprobado ya no se toca; la reproyección siempre se puede ajustar. */
    public function isEditable(): bool
    {
        return $this->version === PlanVersion::Reproyeccion || $this->approved_at === null;
    }

    protected function casts(): array
    {
        return [
            'version' => PlanVersion::class,
            'year' => 'integer',
            'opening_oil_tons' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }
}
