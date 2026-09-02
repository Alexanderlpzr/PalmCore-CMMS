<?php

namespace App\Models;

use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Una persona en la nómina de la empresa.
 *
 * Deliberadamente separado de `User`: el operario de prensa existe para la nómina, no
 * para el CMMS, y ninguno de los 48 de la extractora inicia sesión. `user` queda como
 * enlace opcional para los pocos que sí entran.
 *
 * @property-read string $full_name
 */
#[Fillable([
    'tenant_id',
    'plant_id',
    'user_id',
    'document_type',
    'document_number',
    'first_name',
    'last_name',
    'position',
    'base_salary',
    'salary_type',
    'excluded_from_overtime',
    'transport_allowance_override',
    'hire_date',
    'termination_date',
    'status',
    'eps',
    'pension_fund',
    'severance_fund',
    'arl_risk_class',
    'notes',
])]
class Employee extends BaseModel
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $table = 'hr_employees';

    // ── Relationships ─────────────────────────────────────────────────────────

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(EmployeeQrCode::class)->where('is_active', true);
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(EmployeeQrCode::class);
    }

    public function attendanceScans(): HasMany
    {
        return $this->hasMany(AttendanceScan::class);
    }

    public function attendanceDays(): HasMany
    {
        return $this->hasMany(AttendanceDay::class);
    }

    public function novelties(): HasMany
    {
        return $this->hasMany(EmployeeNovelty::class);
    }

    public function bonuses(): HasMany
    {
        return $this->hasMany(EmployeeBonus::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EmploymentStatus::Activo);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getFullNameAttribute(): string
    {
        return $this->fullName();
    }

    /**
     * ¿Causa horas extras y recargos?
     *
     * Dos motivos lo excluyen y son distintos: el trabajador de dirección, confianza y
     * manejo —el supervisor, el jefe, el director— porque la ley no se las reconoce, y
     * el de salario integral porque ya las lleva incorporadas en su remuneración. En la
     * nómina de agosto de la extractora esto cubre a 14 de 48 personas, y es la
     * diferencia entre un reloj que informa y un reloj que inventa pasivo laboral.
     */
    public function earnsOvertime(): bool
    {
        return ! $this->excluded_from_overtime && $this->salary_type !== 'integral';
    }

    /**
     * ¿Le corresponde auxilio de transporte, con el tope vigente en esa fecha?
     *
     * `transport_allowance_override` gana cuando está definido. Existe porque en el libro
     * actual la excepción se hace borrando la fórmula de la celda, y el resultado es un
     * hueco que nadie sabe si fue una decisión o un descuido: en la nómina de agosto hay
     * exactamente uno, y son 224.185 pesos que el empleado no recibió.
     */
    public function isEligibleForTransportAllowance(float $smlmv, float $maxSmlmv): bool
    {
        if ($this->transport_allowance_override !== null) {
            return $this->transport_allowance_override;
        }

        return (float) $this->base_salary <= $smlmv * $maxSmlmv;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'excluded_from_overtime' => 'boolean',
            'transport_allowance_override' => 'boolean',
            'hire_date' => 'date',
            'termination_date' => 'date',
            'status' => EmploymentStatus::class,
        ];
    }
}
