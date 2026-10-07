<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\DTOs\ClassifiedHours;
use App\Domain\HumanResources\Enums\BonusType;
use App\Domain\HumanResources\Enums\NoveltyDayBasis;
use App\Domain\HumanResources\Enums\NoveltyType;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeNovelty;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * La cadena que va de las horas confirmadas al neto a pagar.
 *
 * Reproduce, paso por paso, la que hoy vive en las 78 columnas del libro de Excel de la
 * extractora, y en el mismo orden, para que el paralelo de validación se pueda cuadrar
 * columna contra columna. Donde el libro tiene una decisión discutible, aquí hay un
 * comentario que dice cuál es y por qué se replicó igual: el objetivo de esta fase es
 * reemplazar la herramienta sin cambiar el resultado. Cambiar el resultado viene después,
 * y con el contador delante.
 *
 * Dos divisiones sostienen todo:
 *
 *     valor día  = salario mensual / días del mes (30)
 *     valor hora = salario mensual / divisor de jornada (220)
 *
 * Solo entran las horas **confirmadas**. Una propuesta que nadie firmó no se paga, y esa
 * es la regla que hace que el reloj sea una ayuda y no una autoridad.
 */
class PayrollCalculator
{
    public function __construct(
        private readonly PayrollParameterService $parameters,
        private readonly OvertimeBonusSplitter $splitter,
    ) {}

    /**
     * Liquida a un trabajador en el período de la nómina.
     */
    public function calculate(Employee $employee, PayrollRun $run): PayrollEntry
    {
        $from = CarbonImmutable::instance($run->period_start)->startOfDay();
        $to = CarbonImmutable::instance($run->period_end)->endOfDay();

        $p = $this->parameters->allOn($from, $employee->tenant_id);
        $warnings = $this->parameterWarnings($from, $employee->tenant_id);

        $monthDays = $p[PayrollParameter::MonthDays->value];
        $salary = (float) $employee->base_salary;

        $dayValue = $salary / $monthDays;
        $hourValue = $salary / $p[PayrollParameter::MonthlyHoursDivisor->value];

        // ── Días ──────────────────────────────────────────────────────────────
        $novelties = $this->noveltyDays($employee, $from, $to);
        $noveltyDayCount = array_sum($novelties);

        // Las horas salen de la ventana de corte —del 27 al 26 en la extractora— y el
        // sueldo, del mes. Sin corte, las dos cosas son el período.
        $hoursFrom = CarbonImmutable::instance($run->hoursFrom())->startOfDay();
        $hoursTo = CarbonImmutable::instance($run->hoursTo())->endOfDay();
        $hasCutoff = $run->hasHoursCutoff();

        $confirmedDays = $this->confirmedDays($employee, $hoursFrom, $hoursTo);

        if ($hasCutoff) {
            // Con corte la nómina se liquida antes de que el mes termine: el sueldo no
            // puede salir de días que todavía no ocurren. Se cuenta como el libro, del
            // mes en que estuvo contratado se restan las novedades, y las ausencias se
            // capturan como novedad.
            $workedDays = max(0.0, $this->employedDays($employee, $from, $monthDays) - $noveltyDayCount);
        } else {
            // Los días laborados son los que el reloj confirmó, y las novedades ocupan el
            // resto del mes. El libro los deduce al revés —parte de 30 y resta— porque allí
            // nadie mide la asistencia; aquí sí, así que se toma la medición y las novedades
            // se contrastan contra ella.
            $workedDays = $confirmedDays->sum(fn (AttendanceDay $d): int => $d->jornal());
        }

        $totalDays = $workedDays + $noveltyDayCount;

        if (! $hasCutoff && abs($totalDays - $monthDays) > 0.001) {
            $warnings[] = sprintf(
                'Los días no cuadran: %s trabajados + %s de novedad = %s, y el mes son %s. '
                .'Falta capturar una novedad o confirmar horas.',
                $this->n($workedDays), $this->n($noveltyDayCount), $this->n($totalDays), $this->n($monthDays),
            );
        }

        if (! $hasCutoff && $confirmedDays->isEmpty() && $noveltyDayCount === 0) {
            $warnings[] = 'No hay horas confirmadas ni novedades en el período.';
        }

        $proposedCount = $this->proposedDayCount($employee, $hoursFrom, $hoursTo);

        if ($proposedCount > 0) {
            $warnings[] = "{$proposedCount} días del reloj siguen sin confirmar y no entraron a esta liquidación.";
        }

        // ── Recargos y horas extras ───────────────────────────────────────────
        [$legalHours, $bonusHours] = $this->splitHours($confirmedDays, $from, $employee->tenant_id, $p);

        $buckets = $this->valueHours($legalHours, $hourValue, $p);
        $surchargesTotal = array_sum(array_column($buckets, 'amount'));

        // Lo que la regla del bono sacó de las horas extras: se paga como bonificación
        // constitutiva, con el mismo valor, como la hoja BONIFICACIONES del formato.
        $hoursBonus = array_filter(
            $this->valueHours($bonusHours, $hourValue, $p),
            fn (array $row): bool => $row['hours'] > 0,
        );
        $hoursBonusTotal = array_sum(array_column($hoursBonus, 'amount'));

        // ── Novedades valoradas ───────────────────────────────────────────────
        $smlmvDayValue = $p[PayrollParameter::Smlmv->value] / $monthDays;
        $noveltyValues = $this->valueNovelties($novelties, $dayValue, $smlmvDayValue);

        $paidNovelties = 0.0;
        $absenceDeduction = 0.0;
        $vacationAmount = 0.0;

        foreach ($noveltyValues as $key => $row) {
            $type = NoveltyType::from($key);

            if ($type === NoveltyType::Vacaciones) {
                $vacationAmount = $row['amount'];

                continue;
            }

            if ($type->isPaid()) {
                $paidNovelties += $row['amount'];

                continue;
            }

            // Los días no pagados sí se valoran: es lo que se le descuenta al salario y
            // lo que, en el caso de la ausencia, igual entra al IBC de pensión.
            $absenceDeduction += $row['days'] * $dayValue;
        }

        // ── Devengado ─────────────────────────────────────────────────────────
        // Las vacaciones no entran aquí, y sale del libro: allí se calculan y se cuentan
        // en el total de días, pero no llegan al básico devengado ni al neto. Es la
        // práctica de desembolsarlas aparte. Queda como aviso para que sea una decisión
        // y no un descuido.
        $basicEarned = ($workedDays * $dayValue) + $paidNovelties;
        $earnedWithSurcharges = $basicEarned + $surchargesTotal;

        if ($vacationAmount > 0) {
            $warnings[] = sprintf(
                'Las vacaciones (%s) se cuentan en la base de prima pero no se pagan en esta nómina, '
                .'igual que en el libro actual. Confirme que se desembolsan aparte.',
                $this->money($vacationAmount),
            );
        }

        // ── Bonificaciones ────────────────────────────────────────────────────
        $bonuses = $this->bonuses($employee, $from, $to, $workedDays / $monthDays);
        $bonusesTotal = array_sum($bonuses);

        // ── Auxilio de transporte ─────────────────────────────────────────────
        $transportAllowance = $employee->isEligibleForTransportAllowance(
            $p[PayrollParameter::Smlmv->value],
            $p[PayrollParameter::TransportAllowanceMaxSmlmv->value],
        )
            ? ($p[PayrollParameter::TransportAllowance->value] / $monthDays) * $workedDays
            : 0.0;

        $totalEarned = $earnedWithSurcharges + $bonusesTotal + $hoursBonusTotal + $transportAllowance;

        // ── Bases ─────────────────────────────────────────────────────────────
        // Las cuatro se calculan por separado y ninguna coincide con otra, que es
        // exactamente lo correcto. Ver los comentarios de `NoveltyType`. La bonificación
        // por horas es constitutiva: entra a donde entran las horas extras.
        $ibcHealth = $earnedWithSurcharges + $bonuses[BonusType::Constitutiva->value] + $hoursBonusTotal;

        // El día que no se paga sí cotiza a pensión: la cotización no se interrumpe
        // porque alguien faltó.
        $ibcPension = $ibcHealth + $absenceDeduction;

        $severanceBase = ($workedDays * $dayValue)
            + $this->sumNoveltiesWhere($noveltyValues, fn (NoveltyType $t): bool => $t->countsSeveranceBase())
            + $surchargesTotal
            + $bonuses[BonusType::Constitutiva->value]
            + $hoursBonusTotal
            + $transportAllowance;

        $vacationBase = ($workedDays * $dayValue)
            + $this->sumNoveltiesWhere($noveltyValues, fn (NoveltyType $t): bool => $t->countsVacationBase());

        // ── Deducciones ───────────────────────────────────────────────────────
        $healthDeduction = $ibcHealth * $p[PayrollParameter::HealthEmployeeRate->value];
        $pensionDeduction = $ibcPension * $p[PayrollParameter::PensionEmployeeRate->value];

        $recurring = $this->recurringDeductions($employee, $from);
        $otherDeductions = array_sum(array_column($recurring, 'amount'));

        // El fondo de solidaridad pensional y la retención en la fuente no se calculan
        // todavía: son tablas progresivas que dependen del UVT y de las deducciones
        // personales de cada trabajador, y en el libro actual están escritas a mano para
        // un solo empleado. Entran cuando el contador confirme la tabla vigente; hasta
        // entonces se respeta lo que se cargue a mano en vez de inventar un cero
        // silencioso.
        $solidarityFund = 0.0;
        $withholdingTax = 0.0;

        $totalDeducted = $healthDeduction + $pensionDeduction + $solidarityFund
            + $withholdingTax + $otherDeductions;

        $netPay = $totalEarned - $totalDeducted;

        return new PayrollEntry([
            'tenant_id' => $employee->tenant_id,
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->fullName(),
            'document_number' => $employee->document_number,
            'position' => $employee->position,
            'base_salary' => round($salary, 2),
            'day_value' => round($dayValue, 4),
            'hour_value' => round($hourValue, 4),
            'worked_days' => $workedDays,
            'novelty_days' => $noveltyDayCount,
            'total_days' => $totalDays,
            'surcharges_total' => round($surchargesTotal, 2),
            'hours_bonus_total' => round($hoursBonusTotal, 2),
            'hours_bonus_breakdown' => $hoursBonus ?: null,
            'novelty_breakdown' => $noveltyValues ?: null,
            'absence_deduction' => round($absenceDeduction, 2),
            'paid_novelties_amount' => round($paidNovelties, 2),
            'vacation_amount' => round($vacationAmount, 2),
            'basic_earned' => round($basicEarned, 2),
            'earned_with_surcharges' => round($earnedWithSurcharges, 2),
            'bonus_housing' => round($bonuses[BonusType::Vivienda->value], 2),
            'bonus_constitutive' => round($bonuses[BonusType::Constitutiva->value], 2),
            'bonus_non_constitutive' => round($bonuses[BonusType::NoConstitutiva->value], 2),
            'bonuses_total' => round($bonusesTotal, 2),
            'transport_allowance' => round($transportAllowance, 2),
            'total_earned' => round($totalEarned, 2),
            'ibc_health' => round($ibcHealth, 2),
            'ibc_pension' => round($ibcPension, 2),
            'severance_base' => round($severanceBase, 2),
            'vacation_base' => round($vacationBase, 2),
            'health_deduction' => round($healthDeduction, 2),
            'pension_deduction' => round($pensionDeduction, 2),
            'solidarity_fund' => round($solidarityFund, 2),
            'withholding_tax' => round($withholdingTax, 2),
            'other_deductions_breakdown' => $recurring ?: null,
            'other_deductions' => round($otherDeductions, 2),
            'total_deducted' => round($totalDeducted, 2),
            'net_pay' => round($netPay, 2),
            'parameters_snapshot' => $p,
            'warnings' => $warnings ?: null,
        ] + $this->bucketAttributes($buckets));
    }

    // ── Horas ─────────────────────────────────────────────────────────────────

    /** @return Collection<int, AttendanceDay> */
    private function confirmedDays(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return AttendanceDay::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->between($from->toDateString(), $to->toDateString())
            ->confirmed()
            ->get();
    }

    private function proposedDayCount(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return AttendanceDay::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->between($from->toDateString(), $to->toDateString())
            ->proposed()
            ->count();
    }

    /**
     * Las horas del período repartidas entre horas extras y bonificación.
     *
     * Sin la regla del bono todo va a horas extras, como siempre. Con ella, cada día pasa
     * por `OvertimeBonusSplitter`: los del mes anterior al período, enteros a bonificación;
     * los demás, con el tope diario.
     *
     * @param  Collection<int, AttendanceDay>  $days
     * @param  array<string, float>  $p
     * @return array{0: ClassifiedHours, 1: ClassifiedHours} horas extras y recargos, bonificación
     */
    private function splitHours(Collection $days, CarbonImmutable $periodStart, string $tenantId, array $p): array
    {
        $legal = ClassifiedHours::empty();
        $bonus = ClassifiedHours::empty();
        $bonusRule = $this->parameters->isOn(PayrollParameter::OvertimeExcessAsBonus, $periodStart, $tenantId);

        foreach ($days as $day) {
            if (! $bonusRule) {
                $legal = $legal->plus($day->hours());

                continue;
            }

            $parts = $this->splitter->split(
                $day->hours(),
                wholeDayAsBonus: $day->work_date->toDateString() < $periodStart->toDateString(),
                dailyCap: $p[PayrollParameter::MaxOvertimeHoursDay->value],
                isRestDay: (bool) $day->rest_day_worked,
            );

            $legal = $legal->plus($parts['legal']);
            $bonus = $bonus->plus($parts['bonus']);
        }

        return [$legal, $bonus];
    }

    /**
     * Cada bolsa con sus horas, su tarifa y su valor.
     *
     * @param  array<string, float>  $p
     * @return array<string, array{hours: float, rate: float, amount: float}>
     */
    private function valueHours(ClassifiedHours $hours, float $hourValue, array $p): array
    {
        $result = [];

        foreach ($hours->paidBuckets() as $key => $bucket) {
            $rate = $hourValue * $p[$bucket['parameter']->value];

            $result[$key] = [
                'hours' => round($bucket['hours'], 4),
                'rate' => round($rate, 4),
                'amount' => round($bucket['hours'] * $rate, 2),
            ];
        }

        return $result;
    }

    /**
     * Los días del mes en que la persona estuvo contratada, en meses de 30 como el libro:
     * quien entró el 15 tiene 16; quien estuvo todo el mes, los 30.
     */
    private function employedDays(Employee $employee, CarbonImmutable $periodStart, float $monthDays): float
    {
        $month = $periodStart->startOfMonth();
        $hired = $employee->hire_date ? CarbonImmutable::instance($employee->hire_date) : null;
        $left = $employee->termination_date ? CarbonImmutable::instance($employee->termination_date) : null;

        if (($hired && $hired->greaterThan($month->endOfMonth())) || ($left && $left->lessThan($month))) {
            return 0.0;
        }

        $first = $hired && $hired->isSameMonth($month) ? $hired->day : 1;
        $last = $left && $left->isSameMonth($month) ? min($left->day, (int) $monthDays) : (int) $monthDays;

        return (float) max(0, $last - $first + 1);
    }

    /** @return array<string, float> */
    private function bucketAttributes(array $buckets): array
    {
        $attributes = [];

        foreach ($buckets as $key => $row) {
            $attributes["{$key}_hours"] = $row['hours'];
            $attributes["{$key}_amount"] = $row['amount'];
        }

        return $attributes;
    }

    // ── Novedades ─────────────────────────────────────────────────────────────

    /**
     * Días de novedad del período, por tipo.
     *
     * @return array<string, int>
     */
    private function noveltyDays(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return EmployeeNovelty::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->overlapping($from, $to)
            ->get()
            ->groupBy(fn (EmployeeNovelty $n): string => $n->type->value)
            ->map(fn (Collection $group): int => $group->sum(
                fn (EmployeeNovelty $n): int => $n->daysWithin($from, $to),
            ))
            ->filter(fn (int $days): bool => $days > 0)
            ->all();
    }

    /**
     * @param  array<string, int>  $novelties
     * @return array<string, array{days: int, amount: float, label: string}>
     */
    private function valueNovelties(array $novelties, float $dayValue, float $smlmvDayValue): array
    {
        $valued = [];

        foreach ($novelties as $key => $days) {
            $type = NoveltyType::from($key);

            $amount = match ($type->dayValueBasis()) {
                NoveltyDayBasis::OwnSalary => $days * $dayValue,
                NoveltyDayBasis::Smlmv => $days * $smlmvDayValue,
                NoveltyDayBasis::Unpaid => 0.0,
            };

            $valued[$key] = [
                'days' => $days,
                'amount' => round($amount, 2),
                'label' => $type->label(),
            ];
        }

        return $valued;
    }

    /** @param  callable(NoveltyType): bool  $predicate */
    private function sumNoveltiesWhere(array $noveltyValues, callable $predicate): float
    {
        $sum = 0.0;

        foreach ($noveltyValues as $key => $row) {
            if ($predicate(NoveltyType::from($key))) {
                $sum += $row['amount'];
            }
        }

        return $sum;
    }

    // ── Bonificaciones y descuentos ───────────────────────────────────────────

    /**
     * Las proporcionales se pagan por los días laborados, como el auxilio de transporte:
     * el bono de rodamiento del libro va así.
     *
     * @return array<string, float>
     */
    private function bonuses(Employee $employee, CarbonImmutable $from, CarbonImmutable $to, float $workedShare): array
    {
        $totals = array_fill_keys(array_column(BonusType::cases(), 'value'), 0.0);

        EmployeeBonus::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->overlapping($from, $to)
            ->get()
            ->each(function (EmployeeBonus $bonus) use (&$totals, $workedShare): void {
                $totals[$bonus->type->value] += $bonus->prorate_by_worked_days
                    ? (float) $bonus->amount * $workedShare
                    : (float) $bonus->amount;
            });

        return $totals;
    }

    /** @return array<int, array{concept: string, amount: float}> */
    private function recurringDeductions(Employee $employee, CarbonImmutable $on): array
    {
        return EmployeeDeduction::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->effectiveOn($on)
            ->get()
            ->map(fn (EmployeeDeduction $d): array => [
                'concept' => $d->concept,
                'amount' => (float) $d->amount,
            ])
            ->all();
    }

    // ── Avisos ────────────────────────────────────────────────────────────────

    /** @return array<int, string> */
    private function parameterWarnings(CarbonImmutable $on, string $tenantId): array
    {
        $problems = $this->parameters->inconsistentSundayFactors($on, $tenantId);

        if ($problems === []) {
            return [];
        }

        return [sprintf(
            'Hay %d factores de domingo que no cuadran con la base dominical vigente. '
            .'Revíselos en Parámetros de nómina antes de cerrar.',
            count($problems),
        )];
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function money(float $value): string
    {
        return '$ '.number_format($value, 0, ',', '.');
    }
}
