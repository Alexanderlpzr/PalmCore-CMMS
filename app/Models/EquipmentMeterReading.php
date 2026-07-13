<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\MeterReadingUnit;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\EquipmentMeterReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'equipment_id',
    'reading_value',
    'previous_value',
    'delta',
    'accumulated_value',
    'is_reset',
    'reading_unit',
    'recorded_at',
    'recorded_by',
    'notes',
])]
class EquipmentMeterReading extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EquipmentMeterReadingFactory> */
    use HasFactory;

    use HasUuids;

    // No soft deletes — audit trail is immutable

    // ── Relationships ─────────────────────────────────────────────────────────

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'reading_value' => 'float',
            'previous_value' => 'float',
            'delta' => 'float',
            'accumulated_value' => 'float',
            'is_reset' => 'boolean',
            'reading_unit' => MeterReadingUnit::class,
            'recorded_at' => 'datetime',
        ];
    }
}
