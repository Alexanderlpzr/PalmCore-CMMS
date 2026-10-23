<?php

use App\Domain\HumanResources\Enums\NoveltyType;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\MealAllowanceCalculator;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Domain\HumanResources\Services\PresumedWorkdayService;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Jobs\PresumeAdministrativeWorkdaysJob;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\EmployeeNovelty;
use App\Models\EmployeeQrCode;
use App\Models\Holiday;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/*
 * La jornada presunta de los administrativos, pedida por la extractora el 2026-10-22: los
 * de oficina trabajan normal pero no marcan en portería, y nadie va a meter sus horas una
 * por una. Cada día laboral sin marcas es su jornada legal, de 6 a. m. a 2 p. m. con una
 * hora de almuerzo; el día que marcan, mandan las marcas.
 *
 * Hoy es jueves 22/10. Del lunes 12 —festivo— a ayer miércoles 21 hay ocho días laborales:
 * 13, 14, 15, 16, sábado 17, 19, 20 y 21. El domingo 18 no se presume.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota', 'slug' => 'PRESUNTA']);
    CurrentTenant::set($this->tenant);

    $this->parametros = app(PayrollParameterService::class);
    $this->parametros->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));
    $this->parametros->setValue(PayrollParameter::OrdinaryHoursPerDay, 7, Carbon::parse('2026-01-01'), $this->tenant->id);
    $this->parametros->setValue(PayrollParameter::NightWindowStart, 19, Carbon::parse('2026-01-01'), $this->tenant->id);

    Holiday::factory()->create(['tenant_id' => $this->tenant->id, 'holiday_date' => '2026-10-12']);

    $this->administrativo = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'area' => 'administrativo', 'hire_date' => '2025-01-01']);
    $this->carne = EmployeeQrCode::factory()->forEmployee($this->administrativo)->create();

    $this->travelTo(Carbon::parse('2026-10-22 10:00', 'America/Bogota'));
});

function prenderPresuncion(Tenant $tenant, string $desde = '2026-10-12'): void
{
    app(PayrollParameterService::class)->setValue(PayrollParameter::PresumeAdministrativeWorkday, 1, Carbon::parse($desde), $tenant->id);
}

function presumir(Tenant $tenant): Collection
{
    return app(PresumedWorkdayService::class)->fill($tenant, Carbon::parse('2026-10-12'), Carbon::parse('2026-10-22'));
}

/** @return array<string, AttendanceDay> */
function diasDe(Employee $employee): array
{
    return AttendanceDay::query()
        ->where('employee_id', $employee->id)
        ->orderBy('work_date')
        ->get()
        ->keyBy(fn (AttendanceDay $d): string => $d->work_date->toDateString())
        ->all();
}

it('llena los días laborales sin marcas con la jornada legal, sin extras', function (): void {
    prenderPresuncion($this->tenant);

    $filled = presumir($this->tenant);

    $dias = diasDe($this->administrativo);
    expect(array_keys($dias))->toBe(['2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17', '2026-10-19', '2026-10-20', '2026-10-21'])
        ->and($filled)->toHaveCount(1)
        ->and($filled->first()['dates'])->toHaveCount(8);

    $martes = $dias['2026-10-13'];
    expect((float) $martes->ordinary_hours)->toBe(7.0)
        ->and((float) $martes->worked_hours)->toBe(7.0)
        ->and($martes->hours()->overtimeHours())->toBe(0.0);

    $marcas = AttendanceScan::query()->where('employee_id', $this->administrativo->id)->orderBy('scanned_at')->get();
    expect($marcas)->toHaveCount(32)
        ->and($marcas->pluck('source')->unique()->all())->toBe(['manual'])
        ->and($marcas->first()->notes)->toBe(PresumedWorkdayService::NOTE)
        ->and($marcas->first()->scanned_at->setTimezone('America/Bogota')->format('Y-m-d H:i'))->toBe('2026-10-13 06:00');
});

it('cobra el desayuno y el almuerzo como un día normal', function (): void {
    prenderPresuncion($this->tenant);
    presumir($this->tenant);

    $comidas = app(MealAllowanceCalculator::class)->forEmployees(
        [$this->administrativo->id], $this->tenant->id, Carbon::parse('2026-10-13'), Carbon::parse('2026-10-13'), includeUnconfirmed: true,
    );

    expect($comidas[$this->administrativo->id]['counts']['desayuno'] ?? 0)->toBe(1)
        ->and($comidas[$this->administrativo->id]['counts']['almuerzo'] ?? 0)->toBe(1)
        ->and($comidas[$this->administrativo->id]['counts']['merienda'] ?? 0)->toBe(0);
});

it('no presume el día que el administrativo marcó en portería: mandan sus marcas', function (): void {
    prenderPresuncion($this->tenant);
    $portería = app(AttendanceService::class);
    $portería->record($this->carne, at: Carbon::parse('2026-10-14 07:00', 'America/Bogota')->utc());
    $portería->record($this->carne, at: Carbon::parse('2026-10-14 17:00', 'America/Bogota')->utc());

    presumir($this->tenant);

    $miercoles = diasDe($this->administrativo)['2026-10-14'];
    expect((float) $miercoles->worked_hours)->toBe(10.0)
        ->and(AttendanceScan::query()->where('employee_id', $this->administrativo->id)->whereDate('scanned_at', '2026-10-14')->count())->toBe(2);
});

it('no vuelve a presumir el día que talento humano anuló, ni repite lo ya llenado', function (): void {
    prenderPresuncion($this->tenant);
    presumir($this->tenant);

    AttendanceScan::query()
        ->where('employee_id', $this->administrativo->id)
        ->whereBetween('scanned_at', [Carbon::parse('2026-10-15', 'America/Bogota')->utc(), Carbon::parse('2026-10-15 23:59', 'America/Bogota')->utc()])
        ->update(['voided_at' => now()]);
    AttendanceDay::query()->where('employee_id', $this->administrativo->id)->whereDate('work_date', '2026-10-15')->delete();

    expect(presumir($this->tenant))->toBeEmpty()
        ->and(diasDe($this->administrativo))->not->toHaveKey('2026-10-15')
        ->and(AttendanceScan::query()->where('employee_id', $this->administrativo->id)->count())->toBe(28);
});

it('respeta las novedades, la fecha de ingreso y a quien no es administrativo', function (): void {
    prenderPresuncion($this->tenant);
    EmployeeNovelty::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->administrativo->id,
        'type' => NoveltyType::Vacaciones,
        'starts_on' => '2026-10-19',
        'ends_on' => '2026-10-21',
    ]);
    $nuevo = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'area' => 'administrativo', 'hire_date' => '2026-10-20']);
    $operario = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'area' => 'proceso']);

    presumir($this->tenant);

    expect(array_keys(diasDe($this->administrativo)))->not->toContain('2026-10-19', '2026-10-20', '2026-10-21')
        ->and(array_keys(diasDe($nuevo)))->toBe(['2026-10-20', '2026-10-21'])
        ->and(diasDe($operario))->toBeEmpty();
});

it('no hace nada con el parámetro apagado ni antes de que rija', function (): void {
    expect(presumir($this->tenant))->toBeEmpty();

    prenderPresuncion($this->tenant, '2026-10-19');
    presumir($this->tenant);

    expect(array_keys(diasDe($this->administrativo)))->toBe(['2026-10-19', '2026-10-20', '2026-10-21']);
});

it('la corrida de cada noche llena el día de ayer', function (): void {
    prenderPresuncion($this->tenant);

    (new PresumeAdministrativeWorkdaysJob($this->tenant->id))->handle(app(PresumedWorkdayService::class));

    // Revisa la última semana: del jueves 15 a ayer miércoles 21, sin el domingo.
    expect(array_keys(diasDe($this->administrativo)))->toBe(['2026-10-15', '2026-10-16', '2026-10-17', '2026-10-19', '2026-10-20', '2026-10-21']);
});

it('el comando muestra el plan sin escribir y con --apply prende el parámetro desde la primera marca', function (): void {
    $operario = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'area' => 'proceso']);
    $carne = EmployeeQrCode::factory()->forEmployee($operario)->create();
    app(AttendanceService::class)->record($carne, at: Carbon::parse('2026-10-16 06:00', 'America/Bogota')->utc());

    $this->artisan('attendance:presume-workdays', ['--tenant' => 'PRESUNTA'])->assertSuccessful();

    expect(diasDe($this->administrativo))->toBeEmpty()
        ->and($this->parametros->isOn(PayrollParameter::PresumeAdministrativeWorkday, Carbon::parse('2026-10-16'), $this->tenant->id))->toBeFalse();

    $this->artisan('attendance:presume-workdays', ['--tenant' => 'PRESUNTA', '--apply' => true])->assertSuccessful();

    expect(array_keys(diasDe($this->administrativo)))->toBe(['2026-10-16', '2026-10-17', '2026-10-19', '2026-10-20', '2026-10-21'])
        ->and($this->parametros->isOn(PayrollParameter::PresumeAdministrativeWorkday, Carbon::parse('2026-10-16'), $this->tenant->id))->toBeTrue()
        ->and($this->parametros->isOn(PayrollParameter::PresumeAdministrativeWorkday, Carbon::parse('2026-10-15'), $this->tenant->id))->toBeFalse();
});
