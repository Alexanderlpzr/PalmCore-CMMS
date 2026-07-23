<?php

namespace App\Models;

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Enums\EquipmentPriority;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Assets\Enums\MeterCaptureMode;
use App\Domain\Assets\Enums\MeterReadingFrequency;
use App\Domain\Maintenance\Enums\MeterReadingUnit;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id',
    'plant_id',
    'area_id',
    'category_id',
    'manufacturer_id',
    'supplier_id',
    'parent_equipment_id',
    'code',
    'name',
    'model',
    'serial_number',
    'asset_tag',
    'status',
    'criticality',
    'priority',
    'purchase_date',
    'installation_date',
    'commissioning_date',
    'warranty_expiry_date',
    'useful_life_years',
    'purchase_price',
    'replacement_cost',
    'currency_code',
    'location_notes',
    'technical_specs',
    'notes',
    'is_active',
    'retired_at',
    'retired_reason',
    'created_by',
    'updated_by',
    'current_meter_reading',
    'accumulated_meter_reading',
    'meter_unit',
    'reading_frequency',
    'meter_capture_mode',
    'last_failure_at',
])]
class Equipment extends BaseModel
{
    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'category_id');
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Structural parent (this equipment is a component of the parent). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'parent_equipment_id');
    }

    /** Direct sub-components of this equipment. */
    public function children(): HasMany
    {
        return $this->hasMany(Equipment::class, 'parent_equipment_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EquipmentDocument::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(EquipmentPhoto::class)->orderBy('sort_order');
    }

    public function primaryPhoto(): HasOne
    {
        return $this->hasOne(EquipmentPhoto::class)->where('is_primary', true);
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(EquipmentQrCode::class);
    }

    public function issueReports(): HasMany
    {
        return $this->hasMany(EquipmentIssueReport::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(EquipmentMeterReading::class)->orderByDesc('recorded_at');
    }

    public function latestMeterReading(): HasOne
    {
        return $this->hasOne(EquipmentMeterReading::class)->latestOfMany('recorded_at');
    }

    public function components(): HasMany
    {
        return $this->hasMany(EquipmentComponent::class)->orderBy('name');
    }

    public function failureModeAnalyses(): HasMany
    {
        return $this->hasMany(FailureModeAnalysis::class);
    }

    public function downtimeEvents(): HasMany
    {
        return $this->hasMany(EquipmentDowntimeEvent::class)->orderByDesc('started_at');
    }

    public function ongoingDowntimeEvent(): HasOne
    {
        return $this->hasOne(EquipmentDowntimeEvent::class)->whereNull('ended_at');
    }

    public function kpi(): HasOne
    {
        return $this->hasOne(EquipmentKpi::class);
    }

    public function lastWorkOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class)->latestOfMany('created_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EquipmentStatus::Active->value);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('criticality', EquipmentCriticality::Critical->value);
    }

    public function scopeByPriority(Builder $query, EquipmentPriority $priority): Builder
    {
        return $query->where('priority', $priority->value);
    }

    public function scopeTopPriority(Builder $query): Builder
    {
        return $query->orderByRaw("
            CASE criticality
                WHEN 'critical' THEN 4
                WHEN 'high'     THEN 3
                WHEN 'medium'   THEN 2
                ELSE 1
            END DESC
        ")->orderByRaw("
            CASE priority
                WHEN 'p1' THEN 4
                WHEN 'p2' THEN 3
                WHEN 'p3' THEN 2
                ELSE 1
            END DESC
        ");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isUnderMaintenance(): bool
    {
        return $this->status === EquipmentStatus::UnderMaintenance->value;
    }

    public function isCritical(): bool
    {
        return $this->criticality === EquipmentCriticality::Critical->value;
    }

    /** Total invertido en repuestos/componentes instalados a lo largo de la vida del equipo. */
    public function componentsInvestmentTotal(): float
    {
        return (float) $this->components()->sum('unit_cost');
    }

    /**
     * Proporción entre lo invertido en componentes y el costo de reposición del
     * equipo. Null cuando no hay costo de reposición configurado (no hay base
     * de comparación).
     */
    public function componentsInvestmentRatio(): ?float
    {
        $replacementCost = (float) $this->replacement_cost;

        if ($replacementCost <= 0.0) {
            return null;
        }

        return $this->componentsInvestmentTotal() / $replacementCost;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'status' => EquipmentStatus::class,
            'criticality' => EquipmentCriticality::class,
            'priority' => EquipmentPriority::class,
            'purchase_date' => 'date',
            'installation_date' => 'date',
            'commissioning_date' => 'date',
            'warranty_expiry_date' => 'date',
            'retired_at' => 'datetime',
            'useful_life_years' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'replacement_cost' => 'decimal:2',
            'technical_specs' => 'array',
            'is_active' => 'boolean',
            'current_meter_reading' => 'float',
            'accumulated_meter_reading' => 'float',
            'meter_unit' => MeterReadingUnit::class,
            'reading_frequency' => MeterReadingFrequency::class,
            'meter_capture_mode' => MeterCaptureMode::class,
            'last_failure_at' => 'datetime',
        ];
    }
}
