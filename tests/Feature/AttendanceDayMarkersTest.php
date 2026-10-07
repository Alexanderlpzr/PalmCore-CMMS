<?php

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\AttendanceCorrectionService;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Domain\HumanResources\Services\AttendanceDayConfirmer;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Resources\AttendanceDays\Pages\ListAttendanceDays;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * Lo que el reloj no puede deducir y talento humano sí sabe: que un domingo fue un
 * descanso trabajado —todo extra, como en el formato de horas extras— y que un operario
 * de producción trabajó ese día para mantenimiento. Y dos arreglos del armador de días:
 * cada día con sus propios parámetros, y sin el aviso del tope cuando la regla del bono
 * ya le da a dónde ir al exceso.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app(PayrollParameterService::class)->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));

    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->card = EmployeeQrCode::factory()->forEmployee($this->employee)->create();

    $this->rrhh = User::factory()->create(['is_active' => true]);
    $this->rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->rrhh->assignRole('talento-humano');
});

/** Un turno por la puerta, en hora de la planta, y el día que de él calcula el reloj. */
function turnoMarcado(EmployeeQrCode $carne, string $entrada, string $salida): AttendanceDay
{
    app(AttendanceService::class)->record($carne, at: Carbon::parse($entrada, 'America/Bogota')->utc());
    app(AttendanceService::class)->record($carne, at: Carbon::parse($salida, 'America/Bogota')->utc());

    $fecha = Carbon::parse($entrada)->toDateString();
    app(AttendanceDayBuilder::class)->buildForEmployee($carne->employee, Carbon::parse($fecha), Carbon::parse($fecha));

    return AttendanceDay::query()->forTenant($carne->employee->tenant_id)
        ->where('employee_id', $carne->employee_id)
        ->whereDate('work_date', $fecha)
        ->sole();
}

function pantallaDeHoras()
{
    test()->actingAs(test()->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant(test()->tenant);

    return Livewire::test(ListAttendanceDays::class);
}

it('classifies a rest day worked entirely as overtime, and keeps the mark on every rebuild', function (): void {
    // Domingo 11 de octubre, de 7 a 3: por defecto, ocho horas de jornada con recargo.
    $dia = turnoMarcado($this->card, '2026-10-11 07:00', '2026-10-11 15:00');

    expect((float) $dia->sunday_surcharge_hours)->toBe(8.0)
        ->and((float) $dia->overtime_sunday_day_hours)->toBe(0.0);

    $dia = app(AttendanceCorrectionService::class)->setRestDayWorked($dia, true);

    expect($dia->rest_day_worked)->toBeTrue()
        ->and((float) $dia->sunday_surcharge_hours)->toBe(0.0)
        ->and((float) $dia->overtime_sunday_day_hours)->toBe(8.0);

    // Una marca nueva o una reconstrucción no le quitan la marca al día.
    app(AttendanceDayBuilder::class)->buildForEmployee($this->employee, Carbon::parse('2026-10-11'), Carbon::parse('2026-10-11'));

    expect((float) $dia->refresh()->overtime_sunday_day_hours)->toBe(8.0);
});

it('gives the ordinary shift back when the rest day mark is removed', function (): void {
    $dia = turnoMarcado($this->card, '2026-10-11 07:00', '2026-10-11 15:00');
    $correcciones = app(AttendanceCorrectionService::class);

    $dia = $correcciones->setRestDayWorked($correcciones->setRestDayWorked($dia, true), false);

    expect($dia->rest_day_worked)->toBeFalse()
        ->and((float) $dia->sunday_surcharge_hours)->toBe(8.0)
        ->and((float) $dia->overtime_sunday_day_hours)->toBe(0.0);
});

it('classifies each day with the parameters of that day', function (): void {
    // La jornada de 42 horas: siete horas diarias desde el 15 de julio.
    app(PayrollParameterService::class)->setValue(PayrollParameter::OrdinaryHoursPerDay, 7, Carbon::parse('2026-07-15'), $this->tenant->id);

    foreach (['2026-07-14', '2026-07-15'] as $fecha) {
        app(AttendanceService::class)->record($this->card, at: Carbon::parse("{$fecha} 06:00", 'America/Bogota')->utc());
        app(AttendanceService::class)->record($this->card, at: Carbon::parse("{$fecha} 14:00", 'America/Bogota')->utc());
    }

    // Una sola reconstrucción que cruza el cambio.
    $dias = app(AttendanceDayBuilder::class)
        ->buildForEmployee($this->employee, Carbon::parse('2026-07-14'), Carbon::parse('2026-07-15'))
        ->keyBy(fn (AttendanceDay $d): string => $d->work_date->toDateString());

    expect((float) $dias['2026-07-14']->overtime_day_hours)->toBe(0.0)
        ->and((float) $dias['2026-07-15']->ordinary_hours)->toBe(7.0)
        ->and((float) $dias['2026-07-15']->overtime_day_hours)->toBe(1.0);
});

it('does not warn about the daily cap when the bonus rule takes the excess', function (): void {
    $sinRegla = turnoMarcado($this->card, '2026-10-05 06:00', '2026-10-05 18:00');

    expect(collect($sinRegla->anomalies)->filter(fn (string $a): bool => str_contains($a, 'tope legal')))->toHaveCount(1);

    app(PayrollParameterService::class)->setValue(PayrollParameter::OvertimeExcessAsBonus, 1, Carbon::parse('2026-01-01'), $this->tenant->id);
    $conRegla = turnoMarcado($this->card, '2026-10-06 06:00', '2026-10-06 18:00');

    expect(collect($conRegla->anomalies)->filter(fn (string $a): bool => str_contains($a, 'tope legal')))->toBeEmpty();
});

it('lets human resources mark a rest day worked from «Horas por confirmar»', function (): void {
    $dia = turnoMarcado($this->card, '2026-10-11 07:00', '2026-10-11 15:00');

    pantallaDeHoras()
        ->callAction(TestAction::make('descansoTrabajado')->table($dia))
        ->assertNotified('Día marcado como descanso trabajado');

    expect($dia->refresh()->rest_day_worked)->toBeTrue()
        ->and((float) $dia->overtime_sunday_day_hours)->toBe(8.0);
});

it('offers the rest day only to someone who earns overtime, on a day not yet confirmed', function (): void {
    $dia = turnoMarcado($this->card, '2026-10-11 07:00', '2026-10-11 15:00');

    // El supervisor de la fábrica no causa extras: dirección, confianza y manejo.
    $directivo = Employee::factory()->supervisor()->create(['tenant_id' => $this->tenant->id]);
    $diaDirectivo = turnoMarcado(EmployeeQrCode::factory()->forEmployee($directivo)->create(), '2026-10-11 07:00', '2026-10-11 15:00');

    pantallaDeHoras()
        ->assertActionVisible(TestAction::make('descansoTrabajado')->table($dia))
        ->assertActionHidden(TestAction::make('descansoTrabajado')->table($diaDirectivo));

    app(AttendanceDayConfirmer::class)->confirm($dia, $this->rrhh);

    pantallaDeHoras()
        ->filterTable('status', AttendanceDayStatus::Confirmada->value)
        ->assertActionHidden(TestAction::make('descansoTrabajado')->table($dia));
});

it('marks maintenance support even on a confirmed day, without touching the hours', function (): void {
    $dia = turnoMarcado($this->card, '2026-10-05 06:00', '2026-10-05 14:00');
    app(AttendanceDayConfirmer::class)->confirm($dia, $this->rrhh);
    $horas = (float) $dia->refresh()->worked_hours;

    pantallaDeHoras()
        ->filterTable('status', AttendanceDayStatus::Confirmada->value)
        ->callAction(TestAction::make('apoyoMantenimiento')->table($dia))
        ->assertNotified('Horas cargadas a «Apoyo a mantenimiento»');

    expect($dia->refresh()->maintenance_support)->toBeTrue()
        ->and($dia->status)->toBe(AttendanceDayStatus::Confirmada)
        ->and((float) $dia->worked_hours)->toBe($horas);
});
