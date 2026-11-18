<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\OilTankReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que tenía un tanque al cierre de un día: las toneladas y la acidez. Es el nivel
 * después de los cargues del día.
 */
#[Fillable([
    'tenant_id',
    'oil_tank_id',
    'reading_date',
    'level_tons',
    'acidity_percent',
    'recorded_by',
])]
class OilTankReading extends BaseModel
{
    /** @use HasFactory<OilTankReadingFactory> */
    use HasFactory;

    public function tank(): BelongsTo
    {
        return $this->belongsTo(OilTank::class, 'oil_tank_id');
    }

    protected function casts(): array
    {
        return [
            'reading_date' => 'date',
            'level_tons' => 'decimal:2',
            'acidity_percent' => 'decimal:2',
        ];
    }
}
