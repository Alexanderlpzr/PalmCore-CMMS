<?php

namespace App\Models;

use App\Domain\Assets\Enums\EquipmentDowntimeCauseType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Maintenance\Enums\FailureMode;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\EquipmentDowntimeEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'plant_id',
    'equipment_id',
    'work_order_id',
    'work_order_number',
    'started_at',
    'ended_at',
    'duration_minutes',
    'cause_type',
    'stoppage_category',
    'stoppage_cause',
    'was_planned',
    'affects_production',
    'source',
    'failure_mode',
    'notes',
    'reported_by',
    'registered_by',
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

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOngoing(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /** Stoppages that actually cost the plant programmed production hours. */
    public function scopeProductionAffecting(Builder $query): Builder
    {
        return $query->where('affects_production', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOngoing(): bool
    {
        return $this->ended_at === null;
    }

    /** A paro with no equipment is a plant-wide stoppage (falta de fruta, energía). */
    public function isPlantWide(): bool
    {
        return $this->equipment_id === null;
    }

    /**
     * Minutes lost, falling back to the timestamps when nobody typed an explicit
     * duration. Null while the paro is still open — it has no length yet.
     */
    public function elapsedMinutes(): ?int
    {
        if ($this->duration_minutes !== null) {
            return (int) $this->duration_minutes;
        }

        if ($this->ended_at === null) {
            return null;
        }

        return (int) round($this->started_at->diffInMinutes($this->ended_at));
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'cause_type' => EquipmentDowntimeCauseType::class,
            'stoppage_category' => StoppageCategory::class,
            'failure_mode' => FailureMode::class,
            'was_planned' => 'boolean',
            'affects_production' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
