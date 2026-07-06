<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\EquipmentKpiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

// Does NOT extend BaseModel — KPIs are computed cache, not auditable business entities.

#[Fillable([
    'tenant_id',
    'equipment_id',
    'period_months',
    'period_start',
    'period_end',
    'mtbf_hours',
    'mttr_hours',
    'availability_percentage',
    'unplanned_availability_percentage',
    'failure_count',
    'downtime_hours',
    'operating_hours',
    'mtbf_basis',
    'last_failure_at',
    'last_calculated_at',
    'is_stale',
    'deleted_at',
])]
class EquipmentKpi extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EquipmentKpiFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isFresh(): bool
    {
        return ! $this->is_stale;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'mtbf_hours' => 'decimal:2',
            'mttr_hours' => 'decimal:2',
            'availability_percentage' => 'decimal:2',
            'unplanned_availability_percentage' => 'decimal:2',
            'downtime_hours' => 'decimal:2',
            'operating_hours' => 'decimal:2',
            'last_failure_at' => 'datetime',
            'last_calculated_at' => 'datetime',
            'is_stale' => 'boolean',
        ];
    }
}
