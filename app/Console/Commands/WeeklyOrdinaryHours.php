<?php

namespace App\Console\Commands;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Enums\PayrollRunStatus;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Domain\HumanResources\Services\EmployeePeriodSummary;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enciende la jornada semanal —ordinario hasta completar las horas de la semana, extra solo
 * lo que pase— y rehace con ella los días desde la fecha en que rige, confirmados o no. La
 * firma de quien confirmó se conserva; cambian las horas.
 *
 * Sin `--apply` solo muestra cuánto cambiaría cada trabajador: lo calcula de verdad y
 * deshace todo al final. Se niega si una nómina cerrada cubre esos días.
 */
#[Signature('payroll:weekly-hours
    {--tenant= : Slug de la empresa}
    {--hours=42 : Horas de la jornada semanal (lunes a domingo); 0 vuelve a la jornada diaria}
    {--from= : Desde qué día rige (AAAA-MM-DD); por omisión, el inicio del corte en curso}
    {--apply : Escribe el parámetro y recalcula; sin esto solo muestra el plan}')]
#[Description('Cuenta la jornada por semana (42 h) y recalcula las horas extras del corte en curso')]
class WeeklyOrdinaryHours extends Command
{
    public function handle(PayrollParameterService $parameters, AttendanceDayBuilder $builder): int
    {
        $tenant = Tenant::withoutGlobalScopes()->where('slug', (string) $this->option('tenant'))->first();

        if (! $tenant) {
            $this->error('No encontré esa empresa. Pásela con --tenant=<slug>.');

            return self::FAILURE;
        }

        CurrentTenant::set($tenant);
        $hours = (float) $this->option('hours');
        $today = CarbonImmutable::parse(CarbonImmutable::now($tenant->plantTimezone())->toDateString());
        $cutoffDay = (int) $parameters->valueOrDefault(PayrollParameter::HoursCutoffDay, $today, $tenant->id);
        $from = $this->option('from')
            ? CarbonImmutable::parse((string) $this->option('from'))
            : PayrollRun::hoursWindowContaining($today, $cutoffDay)[0];

        if ($closed = $this->closedRun($tenant, $from, $today)) {
            $this->error("La nómina «{$closed->name}» ya está cerrada sobre esos días: reábrala antes de recalcular.");

            return self::FAILURE;
        }

        $employees = Employee::query()->forTenant($tenant->id)->active()->orderBy('first_name')->get();
        $before = $this->totals($tenant, $from, $today);

        DB::beginTransaction();

        try {
            $parameters->setValue(
                PayrollParameter::WeeklyOrdinaryHours,
                $hours,
                $from,
                $tenant->id,
                notes: $hours > 0 ? "Jornada de {$hours} h por semana: extra solo lo que pase." : 'Jornada contada por día.',
            );

            foreach ($employees as $employee) {
                $builder->buildForEmployee($employee, $from, $today, includeConfirmed: true);
            }

            $after = $this->totals($tenant, $from, $today);
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $this->option('apply') ? DB::commit() : DB::rollBack();

        $rows = $employees
            ->map(fn (Employee $e): array => [
                $e->fullName(),
                $this->hours($before[$e->id]['overtime'] ?? 0),
                $this->hours($after[$e->id]['overtime'] ?? 0),
                $this->hours($before[$e->id]['surcharges'] ?? 0),
                $this->hours($after[$e->id]['surcharges'] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row[1] !== $row[2] || $row[3] !== $row[4])
            ->values()
            ->all();

        $this->info(sprintf(
            '%s — jornada %s desde el %s%s',
            $tenant->name,
            $hours > 0 ? "de {$hours} h por semana" : 'diaria',
            $from->format('d/m/Y'),
            $this->option('apply') ? '' : ' (plan: no se escribió nada; use --apply)',
        ));
        $this->table(['Trabajador', 'Extras antes', 'Extras después', 'Recargos antes', 'Recargos después'], $rows);
        $this->line(sprintf(
            'Total: extras %s → %s · recargos %s → %s',
            $this->hours(collect($before)->sum('overtime')),
            $this->hours(collect($after)->sum('overtime')),
            $this->hours(collect($before)->sum('surcharges')),
            $this->hours(collect($after)->sum('surcharges')),
        ));

        return self::SUCCESS;
    }

    /** @return array<string, array{overtime: float, surcharges: float}> */
    private function totals(Tenant $tenant, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sum = fn (AttendanceDay $day, array $columns): float => array_sum(array_map(fn (string $c): float => (float) $day->{$c}, $columns));

        return AttendanceDay::query()
            ->forTenant($tenant->id)
            ->between($from->toDateString(), $to->toDateString())
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($days): array => [
                'overtime' => round($days->sum(fn (AttendanceDay $d): float => $sum($d, EmployeePeriodSummary::OVERTIME_COLUMNS)), 2),
                'surcharges' => round($days->sum(fn (AttendanceDay $d): float => $sum($d, EmployeePeriodSummary::SURCHARGE_COLUMNS)), 2),
            ])
            ->all();
    }

    private function closedRun(Tenant $tenant, CarbonImmutable $from, CarbonImmutable $to): ?PayrollRun
    {
        return PayrollRun::query()
            ->forTenant($tenant->id)
            ->where('status', PayrollRunStatus::Cerrada)
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->whereNotNull('hours_from')->whereDate('hours_from', '<=', $to->toDateString())->whereDate('hours_to', '>=', $from->toDateString()))
                ->orWhere(fn ($q) => $q->whereNull('hours_from')->whereDate('period_start', '<=', $to->toDateString())->whereDate('period_end', '>=', $from->toDateString())))
            ->first();
    }

    private function hours(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',').' h';
    }
}
