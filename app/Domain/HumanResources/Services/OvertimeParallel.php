<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\DTOs\ClassifiedHours;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * El paralelo del libro de horas extras: con la misma hora de entrada y de salida que
 * anotó talento humano, ¿reparte el sistema las horas igual que la persona?
 *
 * Cada día del libro pasa por el clasificador del reloj y por la regla del bono con los
 * parámetros de la empresa, y se compara bolsa por bolsa contra lo que se escribió a
 * mano. Las diferencias son de dos clases y el informe no las distingue: errores del
 * libro (un domingo anotado como sábado, un festivo sin recargo) y reglas que el sistema
 * todavía no conoce (un turno de doce horas que se paga sin extras). Las dos hay que
 * mirarlas antes de dejar de llenar el libro.
 *
 * El domingo o festivo que el libro pone entero como extra se compara como descanso
 * trabajado: es lo que talento humano marcaría en «Horas por confirmar».
 */
final class OvertimeParallel
{
    public function __construct(
        private readonly HourClassifier $classifier,
        private readonly OvertimeBonusSplitter $splitter,
        private readonly PayrollParameterService $parameters,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $sheets  lo que devuelve `OvertimeWorkbookReader::read`
     * @param  CarbonImmutable  $periodStart  el mes que se paga: sus días anteriores van a bonificación
     * @return list<array<string, mixed>>
     */
    public function compare(array $sheets, Tenant $tenant, CarbonImmutable $periodStart): array
    {
        $employees = Employee::query()
            ->forTenant($tenant->id)
            ->get()
            ->keyBy(fn (Employee $e): string => preg_replace('/\D/', '', (string) $e->document_number));

        $holidays = Holiday::query()
            ->forTenant($tenant->id)
            ->pluck('holiday_date')
            ->mapWithKeys(fn ($date): array => [CarbonImmutable::parse($date)->toDateString() => true])
            ->all();

        $p = $this->parameters->allOn($periodStart, $tenant->id);
        $bonusRule = $this->parameters->isOn(PayrollParameter::OvertimeExcessAsBonus, $periodStart, $tenant->id);
        $isSurcharged = fn (CarbonImmutable $date): bool => $date->isSunday() || isset($holidays[$date->toDateString()]);

        $results = [];

        foreach ($sheets as $sheet) {
            $employee = $employees[$sheet['document']] ?? null;
            $salary = $employee ? (float) $employee->base_salary : (float) ($sheet['salary'] ?? 0);
            $divisor = (float) ($p[PayrollParameter::MonthlyHoursDivisor->value] ?? 0);
            $hourValue = $divisor > 0 ? $salary / $divisor : 0.0;

            $days = [];
            $totals = ['excelLegal' => 0.0, 'excelBonus' => 0.0, 'systemLegal' => 0.0, 'systemBonus' => 0.0, 'systemBonusValue' => 0.0, 'differentDays' => 0];

            foreach ($sheet['days'] as $day) {
                $system = $this->classify($day, $employee, $tenant, $isSurcharged, $periodStart, $bonusRule, $p);

                $excelLegal = array_sum($day['legal']);
                $excelBonus = array_sum($day['bonus']);
                $systemLegal = array_sum($system['legal']);
                $systemBonus = array_sum($system['bonus']);

                $differs = false;

                foreach (OvertimeWorkbookReader::BUCKETS as $bucket) {
                    if (abs($day['legal'][$bucket] - $system['legal'][$bucket]) > 0.01 || abs($day['bonus'][$bucket] - $system['bonus'][$bucket]) > 0.01) {
                        $differs = true;
                    }
                }

                $totals['excelLegal'] += $excelLegal;
                $totals['excelBonus'] += $excelBonus;
                $totals['systemLegal'] += $systemLegal;
                $totals['systemBonus'] += $systemBonus;
                $totals['differentDays'] += $differs ? 1 : 0;

                foreach ($system['bonus'] as $bucket => $hours) {
                    $totals['systemBonusValue'] += $hours * $hourValue * (float) ($p[$this->parameterFor($bucket)->value] ?? 0);
                }

                $days[] = $day + ['system' => $system, 'differs' => $differs];
            }

            $results[] = [
                'sheet' => $sheet['sheet'],
                'name' => $employee?->fullName() ?? $sheet['name'],
                'document' => $sheet['document'],
                'found' => $employee !== null,
                'earnsOvertime' => $employee?->earnsOvertime() ?? true,
                'excelBonusValue' => $sheet['bonusTotal'],
                'days' => $days,
            ] + $totals;
        }

        return $results;
    }

    /**
     * Lo que haría el sistema con ese día: el turno de la entrada a la salida del libro,
     * clasificado y repartido.
     *
     * @param  array<string, float>  $p
     * @return array{legal: array<string, float>, bonus: array<string, float>, restDay: bool, worked: float}
     */
    private function classify(array $day, ?Employee $employee, Tenant $tenant, callable $isSurcharged, CarbonImmutable $periodStart, bool $bonusRule, array $p): array
    {
        $empty = ['legal' => array_fill_keys(OvertimeWorkbookReader::BUCKETS, 0.0), 'bonus' => array_fill_keys(OvertimeWorkbookReader::BUCKETS, 0.0), 'restDay' => false, 'worked' => 0.0];

        if (! $day['start'] || ! $day['exit'] || ! $day['date']) {
            return $empty;
        }

        [$start, $end] = $this->shift($day['date'], $day['start'], $day['exit']);
        $worked = $start->diffInMinutes($end) / 60;

        // El domingo o festivo que el libro paga entero como extra: descanso trabajado.
        $excelOrdinaryType = $day['legal']['sunday_surcharge'] + $day['legal']['night_surcharge'] + $day['legal']['night_sunday_surcharge']
            + $day['bonus']['sunday_surcharge'] + $day['bonus']['night_surcharge'] + $day['bonus']['night_sunday_surcharge'];
        $excelOvertime = array_sum($day['legal']) + array_sum($day['bonus']) - $excelOrdinaryType;
        $restDay = $isSurcharged($day['date']) && $excelOrdinaryType <= 0 && $excelOvertime >= $worked - 0.5;

        $hours = $this->classifier->classify(
            [[$start, $end]],
            $isSurcharged,
            $this->parameters->valueOn(PayrollParameter::NightWindowStart, $day['date'], $tenant->id),
            $this->parameters->valueOn(PayrollParameter::NightWindowEnd, $day['date'], $tenant->id),
            $restDay ? 0.0 : $this->parameters->valueOn(PayrollParameter::OrdinaryHoursPerDay, $day['date'], $tenant->id),
        );

        if ($employee && ! $employee->earnsOvertime()) {
            $hours = new ClassifiedHours(ordinary: $hours->workedHours());
        }

        $parts = $bonusRule
            ? $this->splitter->split($hours, $day['date']->lt($periodStart), (float) ($p[PayrollParameter::MaxOvertimeHoursDay->value] ?? 0), $restDay)
            : ['legal' => $hours, 'bonus' => ClassifiedHours::empty()];

        return [
            'legal' => $this->buckets($parts['legal']),
            'bonus' => $this->buckets($parts['bonus']),
            'restDay' => $restDay,
            'worked' => round($worked, 2),
        ];
    }

    /**
     * El turno en horas reales. La salida antes de la entrada es del día siguiente; las
     * «12:00pm» después de una entrada por la tarde son la medianoche que el libro anota
     * como mediodía.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function shift(CarbonImmutable $date, string $start, string $exit): array
    {
        $from = $date->setTimeFromTimeString($start);
        $to = $date->setTimeFromTimeString($exit);

        if ($to->hour === 12 && $from->hour >= 13) {
            $to = $to->subHours(12);
        }

        if ($to->lessThanOrEqualTo($from)) {
            $to = $to->addDay();
        }

        return [$from, $to];
    }

    /** @return array<string, float> */
    private function buckets(ClassifiedHours $hours): array
    {
        $result = [];

        foreach ($hours->paidBuckets() as $bucket => $paid) {
            $result[$bucket] = round($paid['hours'], 2);
        }

        return array_merge(array_fill_keys(OvertimeWorkbookReader::BUCKETS, 0.0), $result);
    }

    private function parameterFor(string $bucket): PayrollParameter
    {
        return ClassifiedHours::empty()->paidBuckets()[$bucket]['parameter'];
    }
}
