<?php

namespace App\Models;

use App\Domain\HumanResources\Enums\PayrollRunStatus;
use App\Domain\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La nómina de un período.
 *
 * Mientras está en borrador se puede volver a liquidar cuantas veces haga falta. Al
 * cerrarla las cifras dejan de recalcularse, porque ya se pagaron y se aportaron.
 */
#[Fillable([
    'tenant_id',
    'name',
    'period_start',
    'period_end',
    'hours_from',
    'hours_to',
    'status',
    'calculated_at',
    'closed_at',
    'closed_by',
    'total_earned',
    'total_deducted',
    'total_net',
    'employee_count',
    'notes',
])]
class PayrollRun extends BaseModel
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    protected $table = 'hr_payroll_runs';

    /**
     * Toda nómina nace en borrador. La base de datos ya lo pone por defecto, pero el
     * modelo recién creado no lo sabía: las reglas de quién puede editarla miraban un
     * estado vacío y la pantalla fallaba justo después de guardar la nómina nueva.
     */
    protected $attributes = [
        'status' => 'borrador',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', PayrollRunStatus::Borrador);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /** Los renglones que traen algo que revisar antes de pagar. */
    public function entriesWithWarnings(): HasMany
    {
        return $this->entries()->whereNotNull('warnings');
    }

    // ── Ventana de horas ──────────────────────────────────────────────────────

    /** Desde cuándo entran las horas: el día siguiente al corte, o el inicio del período. */
    public function hoursFrom(): CarbonInterface
    {
        return $this->hours_from ?? $this->period_start;
    }

    /** Hasta cuándo entran las horas: el día de corte, o el fin del período. */
    public function hoursTo(): CarbonInterface
    {
        return $this->hours_to ?? $this->period_end;
    }

    /** ¿Las horas salen de una ventana distinta al período del sueldo? */
    public function hasHoursCutoff(): bool
    {
        return ! $this->hoursFrom()->isSameDay($this->period_start)
            || ! $this->hoursTo()->isSameDay($this->period_end);
    }

    /**
     * La ventana de horas en la que cae un día: con corte 26, el 7 de octubre está en la
     * del 27 de septiembre al 26 de octubre, y el 28 de octubre ya en la de noviembre. Sin
     * corte, el mes calendario.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function hoursWindowContaining(CarbonInterface $day, int $cutoffDay): array
    {
        $day = CarbonImmutable::instance($day)->startOfDay();

        if ($cutoffDay <= 0) {
            return [$day->startOfMonth(), $day->endOfMonth()->startOfDay()];
        }

        $month = $day->day > $cutoffDay ? $day->startOfMonth()->addMonthNoOverflow() : $day->startOfMonth();

        return self::hoursWindowFor($month, $cutoffDay);
    }

    /**
     * La ventana de horas que le toca a un período según el día de corte: con 26, la
     * nómina de octubre toma las horas del 27 de septiembre al 26 de octubre. Sin corte
     * (0), ninguna: las horas son las del período.
     *
     * Con corte 28, la de marzo arranca el 1: el 29 de febrero no existe casi nunca, y
     * Carbon lo corre al día siguiente, que es justo lo que corresponde.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public static function hoursWindowFor(CarbonInterface $periodStart, int $cutoffDay): ?array
    {
        if ($cutoffDay <= 0) {
            return null;
        }

        $month = CarbonImmutable::instance($periodStart)->startOfMonth();

        return [
            $month->subMonthNoOverflow()->addDays($cutoffDay),
            $month->addDays($cutoffDay - 1),
        ];
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'hours_from' => 'date',
            'hours_to' => 'date',
            'status' => PayrollRunStatus::class,
            'calculated_at' => 'datetime',
            'closed_at' => 'datetime',
            'total_earned' => 'decimal:2',
            'total_deducted' => 'decimal:2',
            'total_net' => 'decimal:2',
            'employee_count' => 'integer',
        ];
    }
}
