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
 * One day of the plant's production plan: how many hours it was meant to run.
 *
 * Without this row there is no denominator, and without a denominator there is
 * no plant efficiency — only machine availability, which is a different claim.
 */
#[Fillable([
    'tenant_id',
    'plant_id',
    'calendar_date',
    'programmed_hours',
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
        ];
    }
}
