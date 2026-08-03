<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\ProductionCalendarDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day of the plant's production plan: how many hours it was meant to run,
 * and how much fruit actually went in.
 *
 * Without this row there is no denominator, and without a denominator there is
 * no plant efficiency — only machine availability, which is a different claim.
 * `processed_tons` es el numerador de la productividad, y vive aquí por lo mismo:
 * el planificador cierra el día una sola vez.
 */
#[Fillable([
    'tenant_id',
    'plant_id',
    'calendar_date',
    'programmed_hours',
    'processed_tons',
    'notes',
])]
class ProductionCalendarDay extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ProductionCalendarDayFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'production_calendar';

    // ── Relationships ─────────────────────────────────────────────────────────

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'calendar_date' => 'date',
            'programmed_hours' => 'float',
            'processed_tons' => 'float',
        ];
    }
}
