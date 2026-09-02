<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\PayrollEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El renglón de un trabajador en la nómina de un período: su desprendible, guardado.
 *
 * Lleva copiados el nombre, el cargo y el salario de ese momento porque los tres cambian
 * y el comprobante de agosto tiene que seguir diciendo lo que decía en agosto. Sin
 * `SoftDeletes`: un renglón de una nómina cerrada no se borra, y uno de una nómina en
 * borrador se rehace al volver a liquidar.
 */
#[Fillable([
    'tenant_id',
    'payroll_run_id',
    'employee_id',
    'employee_name',
    'document_number',
    'position',
    'base_salary',
    'day_value',
    'hour_value',
    'worked_days',
    'novelty_days',
    'total_days',
    'night_surcharge_hours', 'night_surcharge_amount',
    'sunday_surcharge_hours', 'sunday_surcharge_amount',
    'night_sunday_surcharge_hours', 'night_sunday_surcharge_amount',
    'overtime_day_hours', 'overtime_day_amount',
    'overtime_night_hours', 'overtime_night_amount',
    'overtime_sunday_day_hours', 'overtime_sunday_day_amount',
    'overtime_sunday_night_hours', 'overtime_sunday_night_amount',
    'surcharges_total',
    'novelty_breakdown',
    'absence_deduction',
    'paid_novelties_amount',
    'vacation_amount',
    'basic_earned',
    'earned_with_surcharges',
    'bonus_housing',
    'bonus_constitutive',
    'bonus_non_constitutive',
    'bonuses_total',
    'transport_allowance',
    'total_earned',
    'ibc_health',
    'ibc_pension',
    'severance_base',
    'vacation_base',
    'health_deduction',
    'pension_deduction',
    'solidarity_fund',
    'withholding_tax',
    'other_deductions_breakdown',
    'other_deductions',
    'total_deducted',
    'net_pay',
    'parameters_snapshot',
    'warnings',
])]
class PayrollEntry extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PayrollEntryFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'hr_payroll_entries';

    /** Las siete bolsas, en el orden en que se imprimen. */
    public const BUCKETS = [
        'night_surcharge' => 'Recargo nocturno',
        'sunday_surcharge' => 'Recargo dominical',
        'night_sunday_surcharge' => 'Recargo nocturno dominical',
        'overtime_day' => 'Hora extra diurna',
        'overtime_night' => 'Hora extra nocturna',
        'overtime_sunday_day' => 'Hora extra dominical diurna',
        'overtime_sunday_night' => 'Hora extra dominical nocturna',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Las líneas de recargos y extras que llevan algo, listas para el desprendible.
     *
     * Las bolsas en cero no se imprimen: un comprobante con siete renglones vacíos hace
     * más difícil ver los dos que sí tienen algo.
     *
     * @return array<int, array{concept: string, hours: float, rate: float, amount: float}>
     */
    public function surchargeLines(): array
    {
        $lines = [];

        foreach (self::BUCKETS as $key => $label) {
            $hours = (float) $this->{"{$key}_hours"};
            $amount = (float) $this->{"{$key}_amount"};

            if ($hours <= 0 && $amount <= 0) {
                continue;
            }

            $lines[] = [
                'concept' => $label,
                'hours' => $hours,
                'rate' => $hours > 0 ? round($amount / $hours, 2) : 0.0,
                'amount' => $amount,
            ];
        }

        return $lines;
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /** Los días del período tienen que sumar los del mes. Si no, algo falta por capturar. */
    public function daysBalance(): float
    {
        return (float) $this->total_days;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        $casts = [
            'base_salary' => 'decimal:2',
            'day_value' => 'decimal:4',
            'hour_value' => 'decimal:4',
            'worked_days' => 'decimal:2',
            'novelty_days' => 'decimal:2',
            'total_days' => 'decimal:2',
            'novelty_breakdown' => 'array',
            'other_deductions_breakdown' => 'array',
            'parameters_snapshot' => 'array',
            'warnings' => 'array',
        ];

        foreach (array_keys(self::BUCKETS) as $bucket) {
            $casts["{$bucket}_hours"] = 'decimal:4';
            $casts["{$bucket}_amount"] = 'decimal:2';
        }

        foreach ([
            'surcharges_total', 'absence_deduction', 'paid_novelties_amount', 'vacation_amount',
            'basic_earned', 'earned_with_surcharges', 'bonus_housing', 'bonus_constitutive',
            'bonus_non_constitutive', 'bonuses_total', 'transport_allowance', 'total_earned',
            'ibc_health', 'ibc_pension', 'severance_base', 'vacation_base',
            'health_deduction', 'pension_deduction', 'solidarity_fund', 'withholding_tax',
            'other_deductions', 'total_deducted', 'net_pay',
        ] as $money) {
            $casts[$money] = 'decimal:2';
        }

        return $casts;
    }
}
