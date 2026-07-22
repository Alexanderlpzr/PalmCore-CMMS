<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\WorkedHoursPeriodType;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\EquipmentWorkedHoursFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'equipment_id',
    'period_type',
    'log_date',
    'hours',
    'recorded_by',
    'notes',
])]
class EquipmentWorkedHours extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EquipmentWorkedHoursFactory> */
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
            'period_type' => WorkedHoursPeriodType::class,
            'log_date' => 'date',
            'hours' => 'float',
        ];
    }
}
