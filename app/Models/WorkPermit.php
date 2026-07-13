<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\WorkPermitStatus;
use App\Domain\Maintenance\Enums\WorkPermitType;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Database\Factories\WorkPermitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El permiso de trabajo de alto riesgo. Un registro legal: no se borra.
 */
#[Fillable([
    'tenant_id',
    'work_order_id',
    'permit_number',
    'permit_type',
    'status',
    'hazards',
    'controls',
    'isolation_points',
    'valid_from',
    'valid_until',
    'issued_by',
    'issued_at',
    'accepted_by',
    'accepted_at',
    'closed_by',
    'closed_at',
    'notes',
])]
class WorkPermit extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<WorkPermitFactory> */
    use HasFactory;

    use HasUuids;

    // No soft deletes — un permiso es un registro legal

    // ── Relationships ─────────────────────────────────────────────────────────

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * ¿Este permiso autoriza el trabajo *ahora*?
     *
     * Tres cosas, y las tres son necesarias: que el ejecutante lo haya firmado, que
     * no se haya vencido y que ya haya empezado a regir. Un permiso emitido para
     * mañana no cubre el trabajo de hoy.
     */
    public function authorizesWorkAt(?CarbonInterface $moment = null): bool
    {
        $moment ??= now();

        return $this->status->authorizesWork()
            && $this->valid_from->lessThanOrEqualTo($moment)
            && $this->valid_until->greaterThan($moment);
    }

    public function isExpired(): bool
    {
        return $this->valid_until->isPast();
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'permit_type' => WorkPermitType::class,
            'status' => WorkPermitStatus::class,
            'isolation_points' => 'array',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'issued_at' => 'datetime',
            'accepted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
