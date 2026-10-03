<?php

namespace App\Models;

use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\AttendanceScanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una marca de portería: esta persona cruzó la puerta a esta hora, en este sentido.
 *
 * Sin `SoftDeletes` a propósito, igual que `WorkOrderTimeLog`: es la prueba de a qué
 * hora entró alguien a la planta y de ahí sale lo que se le paga. Una marca que falta se
 * agrega a mano; una equivocada se anula con su motivo. Ninguna se borra.
 *
 * Las anuladas no cuentan en ninguna parte: las excluye el alcance global
 * {@see self::VALID_SCOPE}. Solo el historial de marcas las pide de vuelta.
 */
#[Fillable([
    'tenant_id',
    'employee_id',
    'employee_qr_code_id',
    'scanned_at',
    'direction',
    'source',
    'recorded_by',
    'gate',
    'notes',
    'voided_at',
    'voided_by',
    'void_reason',
])]
class AttendanceScan extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AttendanceScanFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'hr_attendance_scans';

    public const VALID_SCOPE = 'notVoided';

    protected static function booted(): void
    {
        static::addGlobalScope(self::VALID_SCOPE, fn (Builder $query) => $query->whereNull('voided_at'));
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(EmployeeQrCode::class, 'employee_qr_code_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('scanned_at', $date);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isEntry(): bool
    {
        return $this->direction === AttendanceDirection::Entrada;
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'voided_at' => 'datetime',
            'direction' => AttendanceDirection::class,
        ];
    }
}
