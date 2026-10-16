<?php

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Domain\HumanResources\Services\AttendanceDayConfirmer;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/*
 * La jornada contada por semana: todo es ordinario hasta completar 42 horas de lunes a
 * domingo, y solo lo que pase es extra, sin tope diario. Pedido por la extractora el
 * 2026-10-16 con este caso del libro: turnos de noche largos que en la semana suman 39 h.
 *
 *   lun 28/09 6 p. m. – 2 a. m.    8 h
 *   mar 29/09 6 p. m. – 1 a. m.    7 h
 *   mié 30/09 6 p. m. – 6 a. m.   12 h
 *   jue 01/10 4 p. m. – 9 p. m.    5 h
 *   vie 02/10 4 p. m. – 11 p. m.   7 h
 *                                 ────
 *                                 39 h  → ninguna hora extra
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    CurrentTenant::set($this->tenant);

    $this->parametros = app(PayrollParameterService::class);
    $this->parametros->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));
    // Como en la extractora: 7 h por día y noche desde las 7 p. m.
    $this->parametros->setValue(PayrollParameter::OrdinaryHoursPerDay, 7, Carbon::parse('2026-01-01'), $this->tenant->id);
    $this->parametros->setValue(PayrollParameter::NightWindowStart, 19, Carbon::parse('2026-01-01'), $this->tenant->id);

    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->carne = EmployeeQrCode::factory()->forEmployee($this->employee)->create();

    $this->travelTo(Carbon::parse('2026-10-16 10:00', 'America/Bogota'));
});

function marcarTurno(EmployeeQrCode $carne, string $entrada, string $salida): void
{
    app(AttendanceService::class)->record($carne, at: Carbon::parse($entrada, 'America/Bogota')->utc());
    app(AttendanceService::class)->record($carne, at: Carbon::parse($salida, 'America/Bogota')->utc());
}

function semanaDelLibro(EmployeeQrCode $carne): void
{
    marcarTurno($carne, '2026-09-28 18:00', '2026-09-29 02:00');
    marcarTurno($carne, '2026-09-29 18:00', '2026-09-30 01:00');
    marcarTurno($carne, '2026-09-30 18:00', '2026-10-01 06:00');
    marcarTurno($carne, '2026-10-01 16:00', '2026-10-01 21:00');
    marcarTurno($carne, '2026-10-02 16:00', '2026-10-02 23:00');
}

/** @return array<string, AttendanceDay> los días de la semana del 28/09, por fecha */
function diasDeLaSemana(Employee $employee): array
{
    return AttendanceDay::query()
        ->where('employee_id', $employee->id)
        ->between('2026-09-28', '2026-10-04')
        ->orderBy('work_date')
        ->get()
        ->keyBy(fn (AttendanceDay $d): string => $d->work_date->toDateString())
        ->all();
}

function extrasDe(array $dias): float
{
    return round(array_sum(array_map(fn (AttendanceDay $d): float => $d->hours()->overtimeHours(), $dias)), 2);
}

it('pays the six overtime hours of the book week while the rule is per day', function (): void {
    semanaDelLibro($this->carne);
    app(AttendanceDayBuilder::class)->buildForEmployee($this->employee, Carbon::parse('2026-09-28'), Carbon::parse('2026-10-04'));

    $dias = diasDeLaSemana($this->employee);

    // Lunes 1 h y miércoles 5 h por encima de las 7 h del día.
    expect(extrasDe($dias))->toBe(6.0)
        ->and($dias['2026-09-30']->hours()->overtimeHours())->toBe(5.0);
});

it('pays no overtime in a 39-hour week with the 42-hour weekly rule', function (): void {
    $this->parametros->setValue(PayrollParameter::WeeklyOrdinaryHours, 42, Carbon::parse('2026-09-27'), $this->tenant->id);

    semanaDelLibro($this->carne);
    app(AttendanceDayBuilder::class)->buildForEmployee($this->employee, Carbon::parse('2026-09-28'), Carbon::parse('2026-10-04'));

    $dias = diasDeLaSemana($this->employee);
    $miercoles = $dias['2026-09-30'];

    expect($dias)->toHaveCount(5)
        ->and(extrasDe($dias))->toBe(0.0)
        ->and((float) $miercoles->worked_hours)->toBe(12.0)
        // Las 12 h son ordinarias, y las de la noche siguen con su recargo nocturno.
        ->and($miercoles->hours()->overtimeHours())->toBe(0.0)
        ->and((float) $miercoles->night_surcharge_hours)->toBe(11.0);
});

it('pays as overtime only what passes 42 hours, at the end of the week', function (): void {
    $this->parametros->setValue(PayrollParameter::WeeklyOrdinaryHours, 42, Carbon::parse('2026-09-27'), $this->tenant->id);

    semanaDelLibro($this->carne);
    // El sábado de 6 a. m. a 12 m. lleva la semana a 45 h: las últimas 3 son extra diurna.
    marcarTurno($this->carne, '2026-10-03 06:00', '2026-10-03 12:00');
    app(AttendanceDayBuilder::class)->buildForEmployee($this->employee, Carbon::parse('2026-09-28'), Carbon::parse('2026-10-04'));

    $dias = diasDeLaSemana($this->employee);

    expect(extrasDe($dias))->toBe(3.0)
        ->and((float) $dias['2026-10-03']->overtime_day_hours)->toBe(3.0)
        ->and((float) $dias['2026-10-03']->ordinary_hours)->toBe(3.0);
});

it('re-counts the rest of the week when an earlier day arrives later', function (): void {
    $this->parametros->setValue(PayrollParameter::WeeklyOrdinaryHours, 42, Carbon::parse('2026-09-27'), $this->tenant->id);
    $builder = app(AttendanceDayBuilder::class);

    // El sábado se marca y se arma primero: solo, sus 6 h son ordinarias.
    marcarTurno($this->carne, '2026-10-03 06:00', '2026-10-03 12:00');
    $builder->buildForEmployee($this->employee, Carbon::parse('2026-10-03'), Carbon::parse('2026-10-03'));
    expect(diasDeLaSemana($this->employee)['2026-10-03']->hours()->overtimeHours())->toBe(0.0);

    // Después llegan los días de antes —como al importar el libro—: armar el lunes rehace
    // la semana hasta el domingo, y el sábado queda con sus 3 h extra.
    semanaDelLibro($this->carne);
    $builder->buildForEmployee($this->employee, Carbon::parse('2026-09-28'), Carbon::parse('2026-10-02'));

    expect((float) diasDeLaSemana($this->employee)['2026-10-03']->overtime_day_hours)->toBe(3.0);
});

it('recalculates the cutoff with the command and keeps who confirmed', function (): void {
    $this->parametros->setValue(PayrollParameter::HoursCutoffDay, 26, Carbon::parse('2026-01-01'), $this->tenant->id);
    semanaDelLibro($this->carne);
    app(AttendanceDayBuilder::class)->buildForEmployee($this->employee, Carbon::parse('2026-09-28'), Carbon::parse('2026-10-04'));

    $firma = User::factory()->create();
    $miercoles = diasDeLaSemana($this->employee)['2026-09-30'];
    app(AttendanceDayConfirmer::class)->confirm($miercoles, $firma);

    // Sin --apply solo muestra el plan: nada cambia.
    $this->artisan('payroll:weekly-hours', ['--tenant' => $this->tenant->slug])
        ->expectsOutputToContain('plan: no se escribió nada')
        ->assertSuccessful();

    expect(extrasDe(diasDeLaSemana($this->employee)))->toBe(6.0);

    $this->artisan('payroll:weekly-hours', ['--tenant' => $this->tenant->slug, '--apply' => true])
        ->expectsOutputToContain('desde el 27/09/2026')
        ->assertSuccessful();

    $miercoles->refresh();

    expect(extrasDe(diasDeLaSemana($this->employee)))->toBe(0.0)
        ->and($miercoles->status)->toBe(AttendanceDayStatus::Confirmada)
        ->and($miercoles->confirmed_by)->toBe($firma->id)
        ->and($this->parametros->valueOn(PayrollParameter::WeeklyOrdinaryHours, Carbon::parse('2026-10-01'), $this->tenant->id))->toBe(42.0);
});
