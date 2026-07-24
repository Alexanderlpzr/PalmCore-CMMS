<?php

namespace App\Models;

use App\Domain\Assets\Enums\EquipmentDowntimeCauseType;
use App\Domain\Assets\Enums\PlantSection;
use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Enums\StoppageConfirmationStatus;
use App\Domain\Assets\Enums\StoppageReason;
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
    'section',
    'started_at',
    'ended_at',
    'duration_minutes',
    'cause_type',
    'stoppage_category',
    'stoppage_reason',
    'stoppage_cause',
    'reported_type',
    'was_planned',
    'affects_production',
    'source',
    'failure_mode',
    'notes',
    'reported_by',
    'registered_by',
    'confirmation_status',
    'confirmed_by',
    'confirmed_at',
    'confirmation_notes',
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

    /** El jefe de turno que firmó (o disputó) las horas. */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
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

    /**
     * Stoppages maintenance is accountable for.
     *
     * A paro classified as mechanical/electrical/instrumentation obviously is. So
     * is any paro born from a work order, even while its Tipo I still says «otro»:
     * the OT itself is the proof that maintenance owned the intervention. Judging
     * only by Tipo I would leave every corrective out of the plant's MTBF — which
     * is precisely the failures the number exists to measure.
     */
    public function scopeMaintenanceOwned(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereIn('stoppage_category', StoppageCategory::maintenanceValues())
            ->orWhere('source', 'work_order'));
    }

    /**
     * Paros cerrados que le restan horas a la planta y que producción todavía no
     * firmó. Son las horas que van al informe sin contraparte.
     */
    public function scopeAwaitingConfirmation(Builder $query): Builder
    {
        return $query->whereNotNull('ended_at')
            ->where('affects_production', true)
            ->where('confirmation_status', StoppageConfirmationStatus::Pending->value);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOngoing(): bool
    {
        return $this->ended_at === null;
    }

    /** ¿Producción ya se pronunció sobre estas horas? */
    public function isSignedByProduction(): bool
    {
        return $this->confirmation_status?->isSigned() ?? false;
    }

    /**
     * Un paro solo necesita la firma de producción si le costó horas a la planta y
     * ya terminó: mientras corre no hay horas que firmar, y una falla sin paro no le
     * quitó tiempo a nadie.
     */
    public function requiresProductionConfirmation(): bool
    {
        return $this->affects_production && $this->ended_at !== null;
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
            'stoppage_reason' => StoppageReason::class,
            'section' => PlantSection::class,
            'reported_type' => ReportedStoppageType::class,
            'confirmation_status' => StoppageConfirmationStatus::class,
            'confirmed_at' => 'datetime',
            'failure_mode' => FailureMode::class,
            'was_planned' => 'boolean',
            'affects_production' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
