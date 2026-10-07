<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Exceptions\PayrollParameterException;
use App\Models\PayrollParameterVersion;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Los cambios de la reforma laboral que mueven la nómina, con su fecha, listos para
 * cargarse como vigencias.
 *
 * Existe porque la extractora tenía cargados los valores de antes —divisor 220, jornada
 * de 8 horas, noche desde las 9 p. m., dominical del 80 %— mientras su propio formato de
 * horas extras ya liquidaba con los nuevos. Cada cambio entra como una vigencia desde la
 * fecha de la norma, así que lo liquidado antes conserva los valores con que se liquidó.
 *
 * Un hito anterior a la primera vigencia de la empresa se aplica desde esa primera
 * vigencia: la norma ya regía cuando la empresa empezó a liquidar aquí.
 */
class LaborReformSchedule
{
    public function __construct(private readonly PayrollParameterService $parameters) {}

    /**
     * Los hitos, en orden. La jornada diaria sale de repartir las 42 horas entre los días
     * que trabaja la empresa: 7 horas en seis días, 8,4 en cinco.
     *
     * @return list<array{date: string, law: string, values: array<string, float>}>
     */
    public function milestones(int $workDaysPerWeek = 6): array
    {
        return [
            [
                'date' => '2025-12-25',
                'law' => 'Ley 2466 de 2025: la jornada nocturna empieza a las 7 p. m.',
                'values' => [PayrollParameter::NightWindowStart->value => 19],
            ],
            [
                'date' => '2026-07-01',
                'law' => 'Ley 2466 de 2025: recargo dominical y festivo del 90 %.',
                'values' => $this->sundayFactors(0.90),
            ],
            [
                'date' => '2026-07-15',
                'law' => 'Ley 2101 de 2021: jornada de 42 horas semanales.',
                'values' => [
                    PayrollParameter::MonthlyHoursDivisor->value => 210,
                    PayrollParameter::OrdinaryHoursPerDay->value => round(42 / $workDaysPerWeek, 4),
                ],
            ],
            [
                'date' => '2027-07-01',
                'law' => 'Ley 2466 de 2025: recargo dominical y festivo del 100 %.',
                'values' => $this->sundayFactors(1.00),
            ],
        ];
    }

    /**
     * Lo que cambiaría en la empresa, sin escribir nada.
     *
     * @return list<array{parameter: PayrollParameter, from: CarbonImmutable, before: ?float, after: float, law: string, status: string}>
     */
    public function plan(string $tenantId, CarbonInterface $until, int $workDaysPerWeek = 6): array
    {
        $plan = [];
        // Lo que ya quedó en el plan pesa sobre los hitos siguientes del mismo parámetro.
        $planned = [];

        foreach ($this->milestones($workDaysPerWeek) as $milestone) {
            $date = CarbonImmutable::parse($milestone['date']);

            if ($date->greaterThan($until)) {
                continue;
            }

            foreach ($milestone['values'] as $key => $value) {
                $parameter = PayrollParameter::from($key);
                $first = PayrollParameterVersion::query()
                    ->forTenant($tenantId)
                    ->where('key', $key)
                    ->min('effective_from');

                if ($first === null) {
                    $plan[] = $this->row($parameter, $date, null, $value, $milestone['law'], 'sin vigencias');

                    continue;
                }

                $from = $date->max(CarbonImmutable::parse($first));
                $before = $planned[$key] ?? $this->currentValue($parameter, $from, $tenantId);
                $later = PayrollParameterVersion::query()
                    ->forTenant($tenantId)
                    ->where('key', $key)
                    ->whereDate('effective_from', '>', $from)
                    ->exists();

                $status = match (true) {
                    $before !== null && abs($before - $value) < 0.000001 => 'ya estaba',
                    $later && ! isset($planned[$key]) => 'hay una vigencia posterior',
                    default => 'cambia',
                };

                if ($status === 'cambia') {
                    $planned[$key] = $value;
                }

                $plan[] = $this->row($parameter, $from, $before, $value, $milestone['law'], $status);
            }
        }

        return $plan;
    }

    /**
     * Escribe las vigencias que el plan marca como «cambia».
     *
     * @return list<array{parameter: PayrollParameter, from: CarbonImmutable, before: ?float, after: float, law: string, status: string}>
     */
    public function apply(string $tenantId, CarbonInterface $until, int $workDaysPerWeek = 6, ?string $userId = null): array
    {
        $plan = $this->plan($tenantId, $until, $workDaysPerWeek);

        foreach ($plan as $i => $row) {
            if ($row['status'] !== 'cambia') {
                continue;
            }

            $this->parameters->setValue($row['parameter'], $row['after'], $row['from'], $tenantId, $userId, $row['law']);
            $plan[$i]['status'] = 'aplicado';
        }

        return $plan;
    }

    /** @return array<string, float> los cuatro factores de domingo sobre una misma base */
    private function sundayFactors(float $base): array
    {
        return [
            PayrollParameter::SurchargeSunday->value => $base,
            PayrollParameter::SurchargeNightSunday->value => round($base + 0.35, 4),
            PayrollParameter::OvertimeSundayDay->value => round($base + 1.25, 4),
            PayrollParameter::OvertimeSundayNight->value => round($base + 1.75, 4),
        ];
    }

    private function currentValue(PayrollParameter $parameter, CarbonImmutable $on, string $tenantId): ?float
    {
        try {
            return $this->parameters->valueOn($parameter, $on, $tenantId);
        } catch (PayrollParameterException) {
            return null;
        }
    }

    /** @return array{parameter: PayrollParameter, from: CarbonImmutable, before: ?float, after: float, law: string, status: string} */
    private function row(PayrollParameter $parameter, CarbonImmutable $from, ?float $before, float $after, string $law, string $status): array
    {
        return compact('parameter', 'from', 'before', 'after', 'law', 'status');
    }
}
