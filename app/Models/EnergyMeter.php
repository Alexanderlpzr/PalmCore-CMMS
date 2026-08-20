<?php

namespace App\Models;

use App\Domain\Energy\Enums\EnergySource;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\EnergyMeterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un contador de energía de la planta.
 *
 * No es un `Equipment`, y no por purismo: la red pública no es un activo mantenible, y
 * meter kWh en `equipment_meter_readings` los convertiría en horas de vida de
 * componentes y en horas trabajadas, porque esos consumidores no miran la unidad.
 */
#[Fillable([
    'tenant_id',
    'plant_id',
    'code',
    'name',
    'source',
    'equipment_id',
    'is_active',
    'sort_order',
])]
class EnergyMeter extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EnergyMeterFactory> */
    use HasFactory;

    use HasUuids;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    /** Enlace de reporte. Nullable a propósito: la red pública no tiene equipo. */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(EnergyMeterReading::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** La última lectura registrada, que es contra la que se calcula el próximo delta. */
    public function latestReading(): ?EnergyMeterReading
    {
        return $this->readings()
            ->orderByDesc('reading_date')
            ->orderByDesc('created_at')
            ->first();
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'source' => EnergySource::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
