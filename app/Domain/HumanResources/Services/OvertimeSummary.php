<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\DTOs\ClassifiedHours;
use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Models\AttendanceDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Los totales del formato de horas extras de muchos trabajadores a la vez, para la tabla
 * «Horas extras». Son las mismas cuentas del formato (`FormatoHorasExtrasPdfService`) —
 * mismas horas, misma regla del bono—, pero con una sola consulta para todos en vez de una
 * por trabajador, para que la tabla cargue rápido.
 */
class OvertimeSummary
{
    public const SURCHARGES = ['night_surcharge', 'sunday_surcharge', 'night_sunday_surcharge'];

    public function __construct(
        private readonly PayrollParameterService $parameters,
        private readonly OvertimeBonusSplitter $splitter,
    ) {}

    /**
     * @param  list<string>  $employeeIds
     * @return array<string, array{days: int, unconfirmed: int, legal: array<string, float>, bonus: array<string, float>, overtime: float, surcharges: float, bonusHours: float}>
     */
    public function forEmployees(array $employeeIds, string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($employeeIds === []) {
            return [];
        }

        // El mes que se paga es el del último día de la ventana, como en el formato.
        $periodStart = CarbonImmutable::instance($to)->startOfMonth();
        $bonusRule = $this->parameters->isOn(PayrollParameter::OvertimeExcessAsBonus, $periodStart, $tenantId);
        $cap = $this->parameters->valueOrDefault(PayrollParameter::MaxOvertimeHoursDay, $periodStart, $tenantId, 2);

        $days = AttendanceDay::query()
            ->forTenant($tenantId)
            ->whereIn('employee_id', $employeeIds)
            ->between(CarbonImmutable::instance($from)->toDateString(), CarbonImmutable::instance($to)->toDateString())
            ->get()
            ->groupBy('employee_id');

        $result = [];

        foreach ($days as $employeeId => $employeeDays) {
            $legal = ClassifiedHours::empty();
            $bonus = ClassifiedHours::empty();

            foreach ($employeeDays as $day) {
                if (! $bonusRule) {
                    $legal = $legal->plus($day->hours());

                    continue;
                }

                $parts = $this->splitter->split(
                    $day->hours(),
                    wholeDayAsBonus: $day->work_date->toDateString() < $periodStart->toDateString(),
                    dailyCap: $cap,
                    isRestDay: (bool) $day->rest_day_worked,
                );

                $legal = $legal->plus($parts['legal']);
                $bonus = $bonus->plus($parts['bonus']);
            }

            $legalBuckets = array_map(fn (array $b): float => round($b['hours'], 2), $legal->paidBuckets());
            $bonusBuckets = array_map(fn (array $b): float => round($b['hours'], 2), $bonus->paidBuckets());
            $surcharges = array_sum(array_intersect_key($legalBuckets, array_flip(self::SURCHARGES)));

            $result[$employeeId] = [
                'days' => $employeeDays->filter(fn (AttendanceDay $d): bool => $d->jornal() > 0)->count(),
                'unconfirmed' => $employeeDays->filter(fn (AttendanceDay $d): bool => $d->status === AttendanceDayStatus::Propuesta)->count(),
                'legal' => $legalBuckets,
                'bonus' => $bonusBuckets,
                'overtime' => round(array_sum($legalBuckets) - $surcharges, 2),
                'surcharges' => round($surcharges, 2),
                'bonusHours' => round(array_sum($bonusBuckets), 2),
            ];
        }

        return $result;
    }
}
