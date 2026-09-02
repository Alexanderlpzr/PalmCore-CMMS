<?php

use App\Domain\HumanResources\Enums\BonusType;
use App\Domain\HumanResources\Enums\NoveltyType;
use App\Domain\HumanResources\Enums\PayrollRunStatus;
use App\Domain\HumanResources\Exceptions\PayrollRunException;
use App\Domain\HumanResources\Services\PayrollCalculator;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Domain\HumanResources\Services\PayrollRunService;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeNovelty;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/*
 * La cadena completa, contrastada contra cifras reales del libro de agosto de 2026 de la
 * extractora. Los números que aparecen aquí no son inventados: salen de las columnas del
 * Excel, y cuadrar contra ellos es lo que permitirá reemplazarlo sin sorpresas.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();

    app(PayrollParameterService::class)->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));

    $this->run = PayrollRun::factory()
        ->forPeriod('2026-08-01', '2026-08-30', 'Agosto 2026')
        ->create(['tenant_id' => $this->tenant->id]);
});

function trabajador(array $overrides = []): Employee
{
    return Employee::factory()->create(['tenant_id' => test()->tenant->id] + $overrides);
}

/** Un día confirmado con las horas ya repartidas en las bolsas. */
function diaConfirmado(Employee $employee, string $fecha, array $horas = []): AttendanceDay
{
    return AttendanceDay::factory()->forEmployee($employee)->confirmed()->create([
        'work_date' => $fecha,
        'ordinary_hours' => $horas['ordinary'] ?? 8,
        'night_surcharge_hours' => $horas['night'] ?? 0,
        'sunday_surcharge_hours' => $horas['sunday'] ?? 0,
        'night_sunday_surcharge_hours' => $horas['nightSunday'] ?? 0,
        'overtime_day_hours' => $horas['otDay'] ?? 0,
        'overtime_night_hours' => $horas['otNight'] ?? 0,
        'overtime_sunday_day_hours' => $horas['otSundayDay'] ?? 0,
        'overtime_sunday_night_hours' => $horas['otSundayNight'] ?? 0,
        'worked_hours' => array_sum($horas) ?: 8,
    ]);
}

/** Treinta días de 8 horas ordinarias: el mes completo sin novedades. */
function mesCompleto(Employee $employee, array $horasDelPrimerDia = []): void
{
    for ($d = 1; $d <= 30; $d++) {
        diaConfirmado(
            $employee,
            sprintf('2026-08-%02d', $d),
            $d === 1 ? $horasDelPrimerDia + ['ordinary' => 8] : ['ordinary' => 8],
        );
    }
}

function liquidar(Employee $employee): PayrollEntry
{
    return app(PayrollCalculator::class)->calculate($employee, test()->run);
}

it('calcula el valor día y el valor hora como el libro', function (): void {
    // Analista de Laboratorio: 1.898.894. Valor día 63.296,47 y valor hora 8.631,34.
    $e = trabajador(['base_salary' => 1_898_894]);

    $entry = liquidar($e);

    expect(round((float) $entry->day_value, 2))->toBe(63296.47)
        ->and(round((float) $entry->hour_value, 2))->toBe(8631.34);
});

it('reproduce el renglón del analista de laboratorio', function (): void {
    // Gerson Eduardo Romero Lancheros en el libro: 30 días, 45 h de recargo nocturno,
    // 3 h de nocturno dominical, 14 h extra diurna, 7 h extra nocturna, 3 h extra
    // dominical nocturna, y una bonificación constitutiva de 325.976,80.
    $e = trabajador(['base_salary' => 1_898_894]);

    mesCompleto($e, [
        'night' => 45,
        'nightSunday' => 3,
        'otDay' => 14,
        'otNight' => 7,
        'otSundayNight' => 3,
    ]);

    EmployeeBonus::factory()->forEmployee($e)
        ->of(BonusType::Constitutiva, 325_976.80, '2026-08-01', '2026-08-31')
        ->create();

    $entry = liquidar($e);

    // Cada bolsa, contra el valor total del libro.
    expect(round((float) $entry->night_surcharge_amount))->toBe(135_944.0)      // AE
        ->and(round((float) $entry->night_sunday_surcharge_amount))->toBe(29_778.0)  // AK
        ->and(round((float) $entry->overtime_day_amount))->toBe(151_048.0)      // AN
        ->and(round((float) $entry->overtime_night_amount))->toBe(105_734.0)    // AQ
        ->and(round((float) $entry->overtime_sunday_night_amount))->toBe(66_030.0) // AW
        ->and(round((float) $entry->surcharges_total))->toBe(488_534.0)         // AY
        ->and(round((float) $entry->basic_earned))->toBe(1_898_894.0)           // AC
        ->and(round((float) $entry->earned_with_surcharges))->toBe(2_387_428.0) // AZ
        ->and(round((float) $entry->transport_allowance))->toBe(249_095.0)      // BJ
        ->and(round((float) $entry->total_earned))->toBe(2_962_499.0)           // BK
        ->and(round((float) $entry->ibc_health))->toBe(2_713_404.0)             // BL
        ->and(round((float) $entry->ibc_pension))->toBe(2_713_404.0)            // BM
        ->and(round((float) $entry->health_deduction))->toBe(108_536.0)         // BP
        ->and(round((float) $entry->pension_deduction))->toBe(108_536.0)        // BQ
        ->and(round((float) $entry->total_deducted))->toBe(217_072.0)           // BX
        ->and(round((float) $entry->net_pay))->toBe(2_745_427.0);               // BY
});

it('no paga auxilio de transporte por encima del tope', function (): void {
    // Supervisor con 3.551.508: el tope son dos mínimos, 3.501.810.
    $e = trabajador(['base_salary' => 3_551_508, 'excluded_from_overtime' => true]);
    mesCompleto($e);

    expect((float) liquidar($e)->transport_allowance)->toBe(0.0);
});

it('prorratea el auxilio de transporte por los días trabajados', function (): void {
    // 27 días trabajados y 3 de incapacidad: el auxilio va por los 27.
    $e = trabajador(['base_salary' => 2_600_000]);

    for ($d = 1; $d <= 27; $d++) {
        diaConfirmado($e, sprintf('2026-08-%02d', $d));
    }

    EmployeeNovelty::factory()->forEmployee($e)
        ->of(NoveltyType::IncapacidadEgSalario, '2026-08-28', '2026-08-30')
        ->create();

    $entry = liquidar($e);

    // 249.095 / 30 × 27 = 224.185,50
    expect(round((float) $entry->transport_allowance, 2))->toBe(224_185.50)
        ->and((float) $entry->worked_days)->toBe(27.0)
        ->and((float) $entry->novelty_days)->toBe(3.0)
        ->and((float) $entry->total_days)->toBe(30.0)
        ->and($entry->hasWarnings())->toBeFalse();
});

it('avisa cuando los días no suman los del mes', function (): void {
    $e = trabajador();

    for ($d = 1; $d <= 25; $d++) {
        diaConfirmado($e, sprintf('2026-08-%02d', $d));
    }

    $entry = liquidar($e);

    expect((float) $entry->total_days)->toBe(25.0)
        ->and($entry->hasWarnings())->toBeTrue()
        ->and(implode(' ', $entry->warnings))->toContain('Los días no cuadran');
});

it('no liquida horas que nadie confirmó', function (): void {
    // La regla que hace que el reloj sea una ayuda y no una autoridad.
    $e = trabajador(['base_salary' => 1_898_894]);

    AttendanceDay::factory()->forEmployee($e)->create([
        'work_date' => '2026-08-10',
        'ordinary_hours' => 8,
        'overtime_day_hours' => 4,
        'worked_hours' => 12,
    ]);

    $entry = liquidar($e);

    expect((float) $entry->overtime_day_hours)->toBe(0.0)
        ->and((float) $entry->worked_days)->toBe(0.0)
        ->and(implode(' ', $entry->warnings))->toContain('sin confirmar');
});

it('la bonificación constitutiva entra al IBC y la no constitutiva no', function (): void {
    $e = trabajador(['base_salary' => 1_750_905]);
    mesCompleto($e);

    EmployeeBonus::factory()->forEmployee($e)
        ->of(BonusType::Constitutiva, 400_000, '2026-08-01', '2026-08-31')->create();
    EmployeeBonus::factory()->forEmployee($e)
        ->of(BonusType::NoConstitutiva, 367_849, '2026-08-01', '2026-08-31')->create();

    $entry = liquidar($e);

    expect((float) $entry->bonuses_total)->toBe(767_849.0)
        // El IBC lleva el salario más la constitutiva; la no constitutiva queda fuera.
        ->and((float) $entry->ibc_health)->toBe(2_150_905.0)
        ->and((float) $entry->total_earned)->toBe(1_750_905.0 + 767_849.0 + 249_095.0);
});

it('el día de ausencia no se paga pero sí cotiza a pensión', function (): void {
    $e = trabajador(['base_salary' => 1_750_905]);

    for ($d = 1; $d <= 29; $d++) {
        diaConfirmado($e, sprintf('2026-08-%02d', $d));
    }

    EmployeeNovelty::factory()->forEmployee($e)
        ->of(NoveltyType::AusenciaNoJustificada, '2026-08-30', '2026-08-30')->create();

    $entry = liquidar($e);

    $valorDia = 1_750_905 / 30;

    expect(round((float) $entry->basic_earned, 2))->toBe(round(29 * $valorDia, 2))
        // El IBC de pensión es mayor que el de salud, justamente en el día no pagado.
        ->and(round((float) $entry->ibc_pension - (float) $entry->ibc_health, 2))->toBe(round($valorDia, 2));
});

it('las vacaciones entran a la base de prima pero no al neto del mes', function (): void {
    // Es la decisión del libro y se replica igual, con aviso: quien tomó doce días de
    // vacaciones cobra dieciocho días en esta nómina.
    $e = trabajador(['base_salary' => 1_750_905]);

    for ($d = 1; $d <= 18; $d++) {
        diaConfirmado($e, sprintf('2026-08-%02d', $d));
    }

    EmployeeNovelty::factory()->forEmployee($e)
        ->of(NoveltyType::Vacaciones, '2026-08-19', '2026-08-30')->create();

    $entry = liquidar($e);

    $valorDia = 1_750_905 / 30;

    expect((float) $entry->worked_days)->toBe(18.0)
        ->and((float) $entry->total_days)->toBe(30.0)
        ->and(round((float) $entry->vacation_amount, 2))->toBe(round(12 * $valorDia, 2))
        // El básico solo cubre los 18 días trabajados.
        ->and(round((float) $entry->basic_earned, 2))->toBe(round(18 * $valorDia, 2))
        // Pero la base de prima sí las incluye.
        ->and(round((float) $entry->severance_base, 2))
        ->toBe(round(18 * $valorDia + 12 * $valorDia + (float) $entry->transport_allowance, 2))
        // Y las vacaciones no generan vacaciones.
        ->and(round((float) $entry->vacation_base, 2))->toBe(round(18 * $valorDia, 2))
        ->and(implode(' ', $entry->warnings))->toContain('no se pagan en esta nómina');
});

it('la incapacidad al mínimo se valora sobre el salario mínimo, no sobre el propio', function (): void {
    $e = trabajador(['base_salary' => 3_000_000]);

    for ($d = 1; $d <= 25; $d++) {
        diaConfirmado($e, sprintf('2026-08-%02d', $d));
    }

    EmployeeNovelty::factory()->forEmployee($e)
        ->of(NoveltyType::IncapacidadEgMinimo, '2026-08-26', '2026-08-30')->create();

    $entry = liquidar($e);

    // 5 días × (1.750.905 / 30), no × (3.000.000 / 30).
    expect(round((float) $entry->paid_novelties_amount, 2))->toBe(round(5 * (1_750_905 / 30), 2));
});

it('aplica los descuentos recurrentes vigentes', function (): void {
    $e = trabajador(['base_salary' => 1_750_905]);
    mesCompleto($e);

    EmployeeDeduction::factory()->forEmployee($e)->create([
        'concept' => 'Seguro funerario', 'amount' => 12_750, 'effective_from' => '2026-01-01',
    ]);
    EmployeeDeduction::factory()->forEmployee($e)->create([
        'concept' => 'Póliza', 'amount' => 35_038, 'effective_from' => '2026-01-01',
    ]);
    // Uno que ya terminó: no debe aplicarse.
    EmployeeDeduction::factory()->forEmployee($e)->create([
        'concept' => 'Libranza vieja', 'amount' => 99_999,
        'effective_from' => '2025-01-01', 'effective_to' => '2026-07-31',
    ]);

    $entry = liquidar($e);

    expect((float) $entry->other_deductions)->toBe(47_788.0)
        ->and($entry->other_deductions_breakdown)->toHaveCount(2);
});

it('guarda los parámetros con que se calculó', function (): void {
    $e = trabajador();
    mesCompleto($e);

    $entry = liquidar($e);

    // Se comparan como número y no por tipo: JSON no distingue 220 de 220.0, y la
    // instantánea es para auditar, no para volver a calcular con ella.
    expect($entry->parameters_snapshot)->toHaveKey('monthly_hours_divisor')
        ->and((float) $entry->parameters_snapshot['monthly_hours_divisor'])->toBe(220.0)
        ->and((float) $entry->parameters_snapshot['surcharge_sunday'])->toBe(0.8);
});

it('liquida el período completo y suma los totales', function (): void {
    $uno = trabajador(['base_salary' => 1_750_905]);
    $otro = trabajador(['base_salary' => 1_819_705]);
    mesCompleto($uno);
    mesCompleto($otro);

    $run = app(PayrollRunService::class)->calculate($this->run);

    expect($run->employee_count)->toBe(2)
        ->and($run->entries()->count())->toBe(2)
        ->and((float) $run->total_net)->toBe(
            (float) $run->entries->sum(fn ($e): float => (float) $e->net_pay),
        );
});

it('volver a liquidar no duplica renglones', function (): void {
    $e = trabajador();
    mesCompleto($e);

    $service = app(PayrollRunService::class);
    $service->calculate($this->run);
    $service->calculate($this->run);

    expect($this->run->entries()->count())->toBe(1);
});

it('no deja cerrar con avisos pendientes, salvo que se fuerce', function (): void {
    $e = trabajador();
    // Solo 25 días: queda el aviso de que no cuadran.
    for ($d = 1; $d <= 25; $d++) {
        diaConfirmado($e, sprintf('2026-08-%02d', $d));
    }

    $service = app(PayrollRunService::class);
    $service->calculate($this->run);
    $supervisor = User::factory()->create();

    expect(fn () => $service->close($this->run, $supervisor))
        ->toThrow(PayrollRunException::class);

    $cerrada = $service->close($this->run, $supervisor, force: true);

    expect($cerrada->status)->toBe(PayrollRunStatus::Cerrada)
        ->and($cerrada->closed_by)->toBe($supervisor->id);
});

it('una nómina cerrada no se vuelve a liquidar', function (): void {
    $e = trabajador();
    mesCompleto($e);

    $service = app(PayrollRunService::class);
    $service->calculate($this->run);
    $service->close($this->run, User::factory()->create());

    $service->calculate($this->run);
})->throws(PayrollRunException::class);

it('reabrir devuelve la nómina a borrador y conserva los renglones', function (): void {
    $e = trabajador();
    mesCompleto($e);

    $service = app(PayrollRunService::class);
    $service->calculate($this->run);
    $service->close($this->run, User::factory()->create());

    $reabierta = $service->reopen($this->run);

    expect($reabierta->status)->toBe(PayrollRunStatus::Borrador)
        ->and($reabierta->closed_by)->toBeNull()
        ->and($reabierta->entries()->count())->toBe(1);
});

it('el renglón congela nombre, cargo y salario del momento', function (): void {
    $e = trabajador(['base_salary' => 1_750_905, 'position' => 'Operario de Proceso I']);
    mesCompleto($e);

    $entry = liquidar($e);
    $entry->save();

    $e->update(['base_salary' => 9_000_000, 'position' => 'Gerente', 'first_name' => 'Otro']);

    expect((float) $entry->fresh()->base_salary)->toBe(1_750_905.0)
        ->and($entry->fresh()->position)->toBe('Operario de Proceso I');
});
