<?php

namespace App\Models;

use App\Domain\Assets\Enums\EquipmentDowntimeCauseType;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\EquipmentDowntimeEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'equipment_id',
    'work_order_id',
    'work_order_number',
    'started_at',
    'ended_at',
    'duration_minutes',
    'cause_type',
    'was_planned',
    'failure_mode',
    'notes',
])]
class EquipmentDowntimeEvent extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EquipmentDowntimeEventFactory> */
    use HasFactory;

    use HasUuids;

    // No soft deletes — downtime events are immutable historical facts

    // ── Relationships ─────────────────────────────────────────────────────────

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOngoing(): bool
    {
        return $this->ended_at === null;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'cause_type' => EquipmentDowntimeCauseType::class,
            'was_planned' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
