<?php

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\AttendanceCorrectionService;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Domain\HumanResources\Services\PayrollRunService;
use App\Domain\Reports\Services\FormatoHorasExtrasPdfService;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\PayrollRuns\Pages\EditPayrollRun;
use App\Filament\Resources\PayrollRuns\RelationManagers\EntriesRelationManager;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
use App\Models\Holiday;
use App\Models\PayrollRun;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * El formato de horas extras (TH-FOR-002) hecho por el sistema: una fila por día del
 * corte, con la entrada, el fin del horario y la salida de la puerta, y las horas
 * repartidas entre horas extras y bonificación con la misma regla de la nómina.
 *
 * Hora de 10.000 (2.100.000 / 210), jornada de 7 horas, corte el 26, regla del bono.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $parametros = app(PayrollParameterService::class);
    $parametros->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));

    foreach ([
        [PayrollParameter::MonthlyHoursDivisor, 210],
        [PayrollParameter::OrdinaryHoursPerDay, 7],
        [PayrollParameter::HoursCutoffDay, 26],
        [PayrollParameter::OvertimeExcessAsBonus, 1],
    ] as [$parametro, $valor]) {
        $parametros->setValue($parametro, $valor, Carbon::parse('2026-01-01'), $this->tenant->id);
    }

    Holiday::factory()->on('2026-10-12', 'Día de la Raza')->create(['tenant_id' => $this->tenant->id]);

    $this->employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'base_salary' => 2_100_000,
        'hire_date' => '2025-01-01',
        'position' => 'Mecánico I',
    ]);
    $carne = EmployeeQrCode::factory()->forEmployee($this->employee)->create();

    // 28 de septiembre (mes anterior) y 5 de octubre: de 7 a 6, cuatro extras diurnas.
    // 11 de octubre: domingo de descanso que vino a trabajar, de 7 a 3.
    foreach ([['2026-09-28 07:00', '2026-09-28 18:00'], ['2026-10-05 07:00', '2026-10-05 18:00'], ['2026-10-11 07:00', '2026-10-11 15:00']] as [$entrada, $salida]) {
        app(AttendanceService::class)->record($carne, at: Carbon::parse($entrada, 'America/Bogota')->utc());
        app(AttendanceService::class)->record($carne, at: Carbon::parse($salida, 'America/Bogota')->utc());
    }

    app(AttendanceDayBuilder::class)->buildForEmployee($this->employee, Carbon::parse('2026-09-27'), Carbon::parse('2026-10-26'));

    $domingo = AttendanceDay::query()->where('employee_id', $this->employee->id)->whereDate('work_date', '2026-10-11')->sole();
    app(AttendanceCorrectionService::class)->setRestDayWorked($domingo, true);

    $this->datos = app(FormatoHorasExtrasPdfService::class)->data($this->employee, CarbonImmutable::parse('2026-09-27'), CarbonImmutable::parse('2026-10-26'));
    $this->fila = fn (string $fecha): array => collect($this->datos['rows'])->first(fn (array $r): bool => $r['date']->toDateString() === $fecha);
});

it('lists every day of the cutoff, with the times of the gate', function (): void {
    $dia = ($this->fila)('2026-10-05');

    expect($this->datos['rows'])->toHaveCount(30)
        ->and($dia['entry']->format('H:i'))->toBe('07:00')
        // Inicio más la jornada de siete horas.
        ->and($dia['scheduledEnd']->format('H:i'))->toBe('14:00')
        ->and($dia['exit']->format('H:i'))->toBe('18:00');
});

it('splits the hours with the same rule as the payroll', function (): void {
    // El 28 es de septiembre: entero a bonificación.
    expect(($this->fila)('2026-09-28')['legal']['overtime_day'])->toBe(0.0)
        ->and(($this->fila)('2026-09-28')['bonus']['overtime_day'])->toBe(4.0)
        // El 5 de octubre: dos de horas extras y dos de bonificación.
        ->and(($this->fila)('2026-10-05')['legal']['overtime_day'])->toBe(2.0)
        ->and(($this->fila)('2026-10-05')['bonus']['overtime_day'])->toBe(2.0)
        // El domingo de descanso: ocho extras dominicales, sin tope.
        ->and(($this->fila)('2026-10-11')['legal']['overtime_sunday_day'])->toBe(8.0)
        ->and($this->datos['bonusColumns'])->toBe(['overtime_day']);
});

it('values the hours like the sheet calculator', function (): void {
    expect($this->datos['hourValue'])->toBe(10_000.0)
        ->and($this->datos['calculator']['overtime_day']['legalAmount'])->toBe(25_000.0)
        ->and($this->datos['calculator']['overtime_day']['bonusAmount'])->toBe(75_000.0)
        // 2 × 12.500 + 8 × 20.500
        ->and($this->datos['legalAmount'])->toBe(189_000.0)
        ->and($this->datos['bonusAmount'])->toBe(75_000.0);
});

it('marks Sundays and holidays and explains each day', function (): void {
    expect(($this->fila)('2026-10-11')['surcharged'])->toBeTrue()
        ->and(($this->fila)('2026-10-11')['notes'])->toContain('Descanso trabajado')
        ->and(($this->fila)('2026-10-12')['surcharged'])->toBeTrue()
        ->and(($this->fila)('2026-10-12')['notes'])->toContain('Día de la Raza')
        ->and(($this->fila)('2026-10-05')['notes'])->toContain('Sin confirmar')
        ->and($this->datos['unconfirmed'])->toBe(3);
});

it('prints the PDF', function (): void {
    $pdf = app(FormatoHorasExtrasPdfService::class)->generate($this->employee, CarbonImmutable::parse('2026-09-27'), CarbonImmutable::parse('2026-10-26'));

    expect(substr($pdf, 0, 4))->toBe('%PDF');
});

it('downloads the format from the worker record and from the payroll', function (): void {
    $rrhh = User::factory()->create(['is_active' => true]);
    $rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $rrhh->assignRole('talento-humano');
    $this->actingAs($rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $this->travelTo(Carbon::parse('2026-10-07 10:00', 'America/Bogota'));
    $archivo = "formato-horas-{$this->employee->document_number}-2026-10.pdf";

    // Desde la ficha abre en el corte en curso: del 27 de septiembre al 26 de octubre.
    Livewire::test(EditEmployee::class, ['record' => $this->employee->getRouteKey()])
        ->mountAction('formatoHoras')
        ->assertSchemaStateSet(['desde' => '2026-09-27', 'hasta' => '2026-10-26'], 'mountedActionSchema0')
        ->callMountedAction()
        ->assertFileDownloaded($archivo);

    $nomina = PayrollRun::factory()
        ->forPeriod('2026-10-01', '2026-10-30', 'Nómina de octubre de 2026')
        ->create(['tenant_id' => $this->tenant->id, 'hours_from' => '2026-09-27', 'hours_to' => '2026-10-26']);
    app(PayrollRunService::class)->calculate($nomina);

    Livewire::test(EntriesRelationManager::class, ['ownerRecord' => $nomina, 'pageClass' => EditPayrollRun::class])
        ->callAction(TestAction::make('formatoHoras')->table($nomina->entries()->first()))
        ->assertFileDownloaded($archivo);
});

it('finds the cutoff window a day falls in', function (string $dia, int $corte, array $esperada): void {
    [$desde, $hasta] = PayrollRun::hoursWindowContaining(CarbonImmutable::parse($dia), $corte);

    expect([$desde->toDateString(), $hasta->toDateString()])->toBe($esperada);
})->with([
    'antes del corte' => ['2026-10-07', 26, ['2026-09-27', '2026-10-26']],
    'el día del corte' => ['2026-10-26', 26, ['2026-09-27', '2026-10-26']],
    'después del corte' => ['2026-10-28', 26, ['2026-10-27', '2026-11-26']],
    'sin corte' => ['2026-10-07', 0, ['2026-10-01', '2026-10-31']],
]);
