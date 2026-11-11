<?php

namespace App\Models;

use App\Domain\Safety\Enums\FindingOrigin;
use App\Domain\Safety\Enums\FindingPriority;
use App\Domain\Safety\Enums\FindingStatus;
use App\Domain\Shared\Models\BaseModel;
use App\Infrastructure\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\SstFindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Un hallazgo de seguridad y salud en el trabajo (o ambiental): una condición insegura
 * que SST encontró en la planta, quién debe corregirla y cuándo vence.
 *
 * Su estado sale de sus partes ({@see SstFindingTask}): queda «Ejecutado» cuando todas
 * las áreas responsables terminan, y «Verificado» cuando SST lo revisa.
 */
#[Fillable([
    'tenant_id',
    'number',
    'origin',
    'source_item',
    'reported_on',
    'description',
    'area_id',
    'equipment_id',
    'location_detail',
    'priority',
    'action_plan',
    'due_on',
    'status',
    'photo_before_path',
    'photo_after_path',
    'executed_on',
    'verified_at',
    'verified_by',
    'verification_notes',
    'overdue_notified_at',
    'created_by',
])]
class SstFinding extends BaseModel
{
    /** @use HasFactory<SstFindingFactory> */
    use HasFactory;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(SstFindingTask::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** Pendientes o en ejecución: todavía hay que hacer algo. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [FindingStatus::Pendiente->value, FindingStatus::EnEjecucion->value]);
    }

    /** Abiertos y con el plazo ya cumplido. */
    public function scopeOverdue(Builder $query, ?CarbonImmutable $today = null): Builder
    {
        return $query->open()->whereDate('due_on', '<', ($today ?? self::today())->toDateString());
    }

    /**
     * Hoy en la planta, como fecha: el servidor corre en UTC y a las 7 de la noche de
     * Bogotá ya sería mañana. Se compara por fecha, como las fechas del hallazgo.
     */
    public static function today(?string $tenantId = null): CarbonImmutable
    {
        static $timezones = [];

        $tenantId ??= CurrentTenant::isSet() ? CurrentTenant::id() : null;
        $timezone = $tenantId === null
            ? 'America/Bogota'
            : ($timezones[$tenantId] ??= Tenant::query()->find($tenantId)?->plantTimezone() ?? 'America/Bogota');

        return CarbonImmutable::parse(CarbonImmutable::now($timezone)->toDateString());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function code(): string
    {
        return 'H-'.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }

    /** «hallazgo SST H-001», «hallazgo ambiental H-027». */
    public function originCode(): string
    {
        return $this->origin->adjective().' '.$this->code();
    }

    public function isOverdue(?CarbonImmutable $today = null): bool
    {
        return $this->status->isOpen() && $this->due_on->toDateString() < ($today ?? self::today($this->tenant_id))->toDateString();
    }

    /** Dónde está: la sección, el equipo y el detalle que se haya escrito. */
    public function locationLabel(): string
    {
        // Sin repetir lo mismo con otra tilde: «Esterilización · Esterilizacion».
        return collect([$this->area?->name, $this->equipment?->name, $this->location_detail])
            ->filter()
            ->unique(fn (string $part): string => Str::lower(Str::ascii(trim($part))))
            ->implode(' · ') ?: '—';
    }

    public function photoBeforeUrl(): ?string
    {
        return $this->photo_before_path ? Storage::disk(persistent_disk())->url($this->photo_before_path) : null;
    }

    public function photoAfterUrl(): ?string
    {
        return $this->photo_after_path ? Storage::disk(persistent_disk())->url($this->photo_after_path) : null;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'source_item' => 'integer',
            'origin' => FindingOrigin::class,
            'reported_on' => 'immutable_date',
            'due_on' => 'immutable_date',
            'executed_on' => 'immutable_date',
            'verified_at' => 'immutable_datetime',
            'overdue_notified_at' => 'immutable_datetime',
            'priority' => FindingPriority::class,
            'status' => FindingStatus::class,
        ];
    }
}
