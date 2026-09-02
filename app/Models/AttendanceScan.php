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
 * hora entró alguien a la planta y de ahí sale lo que se le paga. Una marca equivocada
 * se corrige con otra marca manual que deja rastro, nunca borrando la anterior.
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
])]
class AttendanceScan extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AttendanceScanFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'hr_attendance_scans';

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

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'direction' => AttendanceDirection::class,
        ];
    }
}
