<?php

namespace App\Models;

use App\Domain\HumanResources\DTOs\ClassifiedHours;
use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\AttendanceDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El día de trabajo de una persona con sus horas ya clasificadas, a la espera de que un
 * supervisor las confirme.
 *
 * Sin `SoftDeletes`: una propuesta se sobrescribe al reconstruir el período y una
 * confirmada no se borra, se corrige y se vuelve a confirmar.
 */
#[Fillable([
    'tenant_id',
    'employee_id',
    'work_date',
    'ordinary_hours',
    'night_surcharge_hours',
    'sunday_surcharge_hours',
    'night_sunday_surcharge_hours',
    'overtime_day_hours',
    'overtime_night_hours',
    'overtime_sunday_day_hours',
    'overtime_sunday_night_hours',
    'worked_hours',
    'status',
    'confirmed_by',
    'confirmed_at',
    'built_at',
    'source',
    'anomalies',
    'notes',
])]
class AttendanceDay extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AttendanceDayFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'hr_attendance_days';

    // ── Relationships ─────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeProposed(Builder $query): Builder
    {
        return $query->where('status', AttendanceDayStatus::Propuesta);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', AttendanceDayStatus::Confirmada);
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('work_date', [$from, $to]);
    }

    public function scopeWithAnomalies(Builder $query): Builder
    {
        return $query->whereNotNull('anomalies')->whereJsonLength('anomalies', '>', 0);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function hours(): ClassifiedHours
    {
        return new ClassifiedHours(
            ordinary: (float) $this->ordinary_hours,
            nightSurcharge: (float) $this->night_surcharge_hours,
            sundaySurcharge: (float) $this->sunday_surcharge_hours,
            nightSundaySurcharge: (float) $this->night_sunday_surcharge_hours,
            overtimeDay: (float) $this->overtime_day_hours,
            overtimeNight: (float) $this->overtime_night_hours,
            overtimeSundayDay: (float) $this->overtime_sunday_day_hours,
            overtimeSundayNight: (float) $this->overtime_sunday_night_hours,
        );
    }

    public function hasAnomalies(): bool
    {
        return ! empty($this->anomalies);
    }

    /**
     * El jornal del día.
     *
     * Es 1 si la persona estuvo, sin importar cuántas horas: así lo cuenta el libro de
     * Excel, donde la columna de jornales vale siempre 1 y las horas van aparte. Un día
     * con cero horas trabajadas no es un jornal, es una marca que quedó sin cerrar.
     */
    public function jornal(): int
    {
        return $this->worked_hours > 0 ? 1 : 0;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'ordinary_hours' => 'decimal:4',
            'night_surcharge_hours' => 'decimal:4',
            'sunday_surcharge_hours' => 'decimal:4',
            'night_sunday_surcharge_hours' => 'decimal:4',
            'overtime_day_hours' => 'decimal:4',
            'overtime_night_hours' => 'decimal:4',
            'overtime_sunday_day_hours' => 'decimal:4',
            'overtime_sunday_night_hours' => 'decimal:4',
            'worked_hours' => 'decimal:4',
            'status' => AttendanceDayStatus::class,
            'confirmed_at' => 'datetime',
            'built_at' => 'datetime',
            'anomalies' => 'array',
        ];
    }
}
