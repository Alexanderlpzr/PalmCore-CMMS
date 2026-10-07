<?php

use App\Domain\HumanResources\DTOs\ClassifiedHours;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\OvertimeBonusSplitter;
use App\Domain\HumanResources\Services\PayrollCalculator;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/*
 * La nómina como la liquida el formato de horas extras de la extractora (TH-FOR-002):
 * el sueldo del mes, las horas del 27 al 26, y la regla del bono —los días del mes
 * anterior y lo que pasa de dos horas extras por día se pagan como bonificación
 * constitutiva, con el mismo valor—.
 *
 * Sueldo de 2.100.000 con divisor 210: la hora vale 10.000 justos, y cada cifra se
 * puede revisar de cabeza. Factores de la semilla: extra diurna 1,25 (12.500), extra
 * nocturna 1,75 (17.500), recargo nocturno 0,35 (3.500), extra dominical diurna 2,05
 * (20.500).
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();

    $parametros = app(PayrollParameterService::class);
    $parametros->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));
    $parametros->setValue(PayrollParameter::MonthlyHoursDivisor, 210, Carbon::parse('2026-01-01'), $this->tenant->id);
    $parametros->setValue(PayrollParameter::HoursCutoffDay, 26, Carbon::parse('2026-01-01'), $this->tenant->id);
    $parametros->setValue(PayrollParameter::OvertimeExcessAsBonus, 1, Carbon::parse('2026-01-01'), $this->tenant->id);

    $this->run = PayrollRun::factory()
        ->forPeriod('2026-10-01', '2026-10-30', 'Octubre 2026')
        ->create(['tenant_id' => $this->tenant->id, 'hours_from' => '2026-09-27', 'hours_to' => '2026-10-26']);

    $this->employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'base_salary' => 2_100_000,
        'hire_date' => '2025-01-01',
        'termination_date' => null,
        'excluded_from_overtime' => false,
    ]);
});

/** Un día confirmado con sus horas ya en las bolsas. */
function diaDelCorte(Employee $employee, string $fecha, array $horas, bool $descanso = false): AttendanceDay
{
    return AttendanceDay::factory()->forEmployee($employee)->confirmed()->create([
        'work_date' => $fecha,
        'ordinary_hours' => $horas['ordinary'] ?? 0,
        'night_surcharge_hours' => $horas['night'] ?? 0,
        'sunday_surcharge_hours' => $horas['sunday'] ?? 0,
        'night_sunday_surcharge_hours' => $horas['nightSunday'] ?? 0,
        'overtime_day_hours' => $horas['otDay'] ?? 0,
        'overtime_night_hours' => $horas['otNight'] ?? 0,
        'overtime_sunday_day_hours' => $horas['otSundayDay'] ?? 0,
        'overtime_sunday_night_hours' => $horas['otSundayNight'] ?? 0,
        'worked_hours' => array_sum($horas),
        'rest_day_worked' => $descanso,
    ]);
}

function liquidarConCorte(Employee $employee): PayrollEntry
{
    return app(PayrollCalculator::class)->calculate($employee, test()->run);
}

/** Las horas que la regla mandó a bonificación, por bolsa. */
function horasDeBono(PayrollEntry $entry, string $bolsa): float
{
    return (float) ($entry->hours_bonus_breakdown[$bolsa]['hours'] ?? 0);
}

it('pays the days of the previous month entirely as bonus', function (): void {
    // Turno de noche del 28 de septiembre: 6 h de recargo nocturno y 3 extras nocturnas.
    diaDelCorte($this->employee, '2026-09-28', ['ordinary' => 1, 'night' => 6, 'otNight' => 3]);

    $entry = liquidarConCorte($this->employee);

    expect((float) $entry->night_surcharge_hours)->toBe(0.0)
        ->and((float) $entry->overtime_night_hours)->toBe(0.0)
        ->and(horasDeBono($entry, 'night_surcharge'))->toBe(6.0)
        ->and(horasDeBono($entry, 'overtime_night'))->toBe(3.0)
        // 6 × 3.500 + 3 × 17.500
        ->and((float) $entry->hours_bonus_total)->toBe(73_500.0);
});

it('pays two overtime hours a day as overtime and the rest as bonus', function (): void {
    // 7:00 a 2:00 p. m. y salida a las 6:00 p. m.: cuatro extras diurnas.
    diaDelCorte($this->employee, '2026-10-05', ['ordinary' => 7, 'otDay' => 4]);

    $entry = liquidarConCorte($this->employee);

    expect((float) $entry->overtime_day_hours)->toBe(2.0)
        ->and((float) $entry->overtime_day_amount)->toBe(25_000.0)
        ->and(horasDeBono($entry, 'overtime_day'))->toBe(2.0)
        ->and((float) $entry->hours_bonus_total)->toBe(25_000.0);
});

it('keeps the night surcharge as a surcharge inside the month', function (): void {
    diaDelCorte($this->employee, '2026-10-06', ['ordinary' => 1, 'night' => 6, 'otNight' => 3]);

    $entry = liquidarConCorte($this->employee);

    expect((float) $entry->night_surcharge_hours)->toBe(6.0)
        ->and((float) $entry->overtime_night_hours)->toBe(2.0)
        ->and(horasDeBono($entry, 'overtime_night'))->toBe(1.0)
        ->and(horasDeBono($entry, 'night_surcharge'))->toBe(0.0);
});

it('pays a rest day worked entirely as overtime, without the cap', function (): void {
    // El domingo de mantenimiento: ocho extras dominicales, como en el formato.
    diaDelCorte($this->employee, '2026-10-11', ['otSundayDay' => 8], descanso: true);

    $entry = liquidarConCorte($this->employee);

    expect((float) $entry->overtime_sunday_day_hours)->toBe(8.0)
        ->and((float) $entry->overtime_sunday_day_amount)->toBe(164_000.0)
        ->and((float) $entry->hours_bonus_total)->toBe(0.0);
});

it('takes the hours from the 27th to the 26th and nothing outside', function (): void {
    diaDelCorte($this->employee, '2026-09-26', ['ordinary' => 7, 'otDay' => 2]);
    diaDelCorte($this->employee, '2026-10-27', ['ordinary' => 7, 'otDay' => 2]);

    $entry = liquidarConCorte($this->employee);

    expect((float) $entry->overtime_day_hours)->toBe(0.0)
        ->and((float) $entry->hours_bonus_total)->toBe(0.0);
});

it('pays the salary of the month although the hours stop on the 26th', function (): void {
    diaDelCorte($this->employee, '2026-10-05', ['ordinary' => 7]);

    $entry = liquidarConCorte($this->employee);

    expect((float) $entry->worked_days)->toBe(30.0)
        ->and((float) $entry->basic_earned)->toBe(2_100_000.0)
        ->and(collect($entry->warnings)->filter(fn (string $w): bool => str_contains($w, 'no cuadran')))->toBeEmpty();
});

it('counts only the days employed for someone hired in the middle of the month', function (): void {
    $this->employee->update(['hire_date' => '2026-10-15']);

    expect((float) liquidarConCorte($this->employee->fresh())->worked_days)->toBe(16.0);
});

it('adds the hours bonus to the earned total and to the contribution bases', function (): void {
    diaDelCorte($this->employee, '2026-09-28', ['ordinary' => 7, 'otDay' => 4]);
    diaDelCorte($this->employee, '2026-10-05', ['ordinary' => 7, 'otDay' => 4]);

    $entry = liquidarConCorte($this->employee);
    $bono = (float) $entry->hours_bonus_total;

    expect($bono)->toBe(75_000.0)
        ->and((float) $entry->total_earned)->toEqualWithDelta(
            (float) $entry->earned_with_surcharges + (float) $entry->bonuses_total + $bono + (float) $entry->transport_allowance,
            0.01,
        )
        ->and((float) $entry->ibc_health)->toEqualWithDelta((float) $entry->earned_with_surcharges + (float) $entry->bonus_constitutive + $bono, 0.01)
        ->and($entry->hoursBonusLines())->toHaveCount(1)
        ->and($entry->hoursBonusLines()[0]['concept'])->toBe('Hora extra diurna');
});

it('pays everything as overtime when the bonus rule is off, still with the cutoff', function (): void {
    app(PayrollParameterService::class)->setValue(PayrollParameter::OvertimeExcessAsBonus, 0, Carbon::parse('2026-01-01'), $this->tenant->id);

    diaDelCorte($this->employee, '2026-09-28', ['ordinary' => 7, 'otDay' => 4]);
    diaDelCorte($this->employee, '2026-10-05', ['ordinary' => 7, 'otDay' => 4]);

    $entry = liquidarConCorte($this->employee);

    expect((float) $entry->overtime_day_hours)->toBe(8.0)
        ->and((float) $entry->hours_bonus_total)->toBe(0.0)
        ->and($entry->hours_bonus_breakdown)->toBeNull();
});

it('warns about clock days of the window still waiting for confirmation', function (): void {
    AttendanceDay::factory()->forEmployee($this->employee)->create(['work_date' => '2026-09-29', 'worked_hours' => 7, 'ordinary_hours' => 7]);

    expect(liquidarConCorte($this->employee)->warnings)->toContain('1 días del reloj siguen sin confirmar y no entraron a esta liquidación.');
});

describe('the splitter', function (): void {
    it('fills the two legal hours in a fixed order when a day has several kinds', function (): void {
        $partes = (new OvertimeBonusSplitter)->split(
            new ClassifiedHours(ordinary: 7, overtimeDay: 3, overtimeNight: 2),
            wholeDayAsBonus: false,
            dailyCap: 2,
        );

        expect($partes['legal']->overtimeDay)->toBe(2.0)
            ->and($partes['legal']->overtimeNight)->toBe(0.0)
            ->and($partes['bonus']->overtimeDay)->toBe(1.0)
            ->and($partes['bonus']->overtimeNight)->toBe(2.0)
            // Lo que no es extra no se toca.
            ->and($partes['legal']->ordinary)->toBe(7.0)
            ->and($partes['bonus']->ordinary)->toBe(0.0);
    });

    it('treats a day without ordinary shift as a whole overtime day', function (): void {
        $partes = (new OvertimeBonusSplitter)->split(new ClassifiedHours(overtimeSundayDay: 8), wholeDayAsBonus: false, dailyCap: 2);

        expect($partes['legal']->overtimeSundayDay)->toBe(8.0)
            ->and($partes['bonus']->overtimeHours())->toBe(0.0);
    });

    it('sends every overtime hour to the bonus with a cap of zero', function (): void {
        $partes = (new OvertimeBonusSplitter)->split(new ClassifiedHours(ordinary: 7, overtimeDay: 3), wholeDayAsBonus: false, dailyCap: 0);

        expect($partes['legal']->overtimeHours())->toBe(0.0)
            ->and($partes['bonus']->overtimeDay)->toBe(3.0);
    });
});

describe('the hours window of a period', function (): void {
    it('runs from the day after the cutoff of the previous month to the cutoff', function (string $periodStart, int $cutoff, ?array $expected): void {
        $window = PayrollRun::hoursWindowFor(CarbonImmutable::parse($periodStart), $cutoff);

        expect($window ? [$window[0]->toDateString(), $window[1]->toDateString()] : null)->toBe($expected);
    })->with([
        'octubre con corte 26' => ['2026-10-01', 26, ['2026-09-27', '2026-10-26']],
        'enero cruza el año' => ['2027-01-01', 26, ['2026-12-27', '2027-01-26']],
        'marzo con corte 28' => ['2026-03-01', 28, ['2026-03-01', '2026-03-28']],
        'marzo bisiesto con corte 28' => ['2028-03-01', 28, ['2028-02-29', '2028-03-28']],
        'sin corte' => ['2026-10-01', 0, null],
    ]);
});
