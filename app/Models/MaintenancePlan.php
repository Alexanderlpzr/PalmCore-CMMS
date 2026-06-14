<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\MaintenanceTimeFrequency;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\MaintenancePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id',
    'equipment_id',
    'plan_number',
    'name',
    'description',
    'responsible_user_id',
    'trigger_source',
    'time_frequency',
    'meter_interval',
    'cadence_mode',
    'pause_when_equipment_inactive',
    'grace_period_days',
    'grace_meter_hours',
    'estimated_duration_minutes',
    'is_active',
    'last_generated_at',
])]
class MaintenancePlan extends BaseModel
{
    /** @use HasFactory<MaintenancePlanFactory> */
    use HasFactory;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(MaintenancePlanTask::class)->orderBy('sort_order');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MaintenancePlanAttachment::class);
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(MaintenanceSchedule::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isCalendarBased(): bool
    {
        return $this->trigger_source->requiresTimeFrequency();
    }

    public function isMeterBased(): bool
    {
        return $this->trigger_source->requiresMeterInterval();
    }

    public function frequencyLabel(): string
    {
        return match ($this->trigger_source) {
            MaintenanceTriggerSource::Calendar => $this->time_frequency?->label() ?? '—',
            MaintenanceTriggerSource::Meter => ($this->meter_interval ?? '—').'h',
            MaintenanceTriggerSource::Hybrid => ($this->time_frequency?->label() ?? '—').' + '.($this->meter_interval ?? '—').'h',
            MaintenanceTriggerSource::Manual => 'Manual',
        };
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'trigger_source' => MaintenanceTriggerSource::class,
            'time_frequency' => MaintenanceTimeFrequency::class,
            'pause_when_equipment_inactive' => 'boolean',
            'is_active' => 'boolean',
            'last_generated_at' => 'datetime',
        ];
    }
}
