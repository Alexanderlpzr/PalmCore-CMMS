<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\MaintenanceBudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que se le asignó al área de mantenimiento para un mes, en una planta.
 */
#[Fillable([
    'tenant_id',
    'plant_id',
    'year',
    'month',
    'amount',
    'notes',
    'created_by',
])]
class MaintenanceBudget extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<MaintenanceBudgetFactory> */
    use HasFactory;

    use HasUuids;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function periodLabel(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'amount' => 'float',
        ];
    }
}
