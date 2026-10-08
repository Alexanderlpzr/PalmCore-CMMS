<?php

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Exceptions\AttendanceException;
use App\Domain\HumanResources\Services\AttendanceCorrectionService;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Domain\HumanResources\Services\AttendanceDayConfirmer;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Resources\AttendanceDays\Pages\ListAttendanceDays;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
use App\Models\PayrollRun;
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
 * Talento humano corrige un día desde «Horas por confirmar»: la hora de sus marcas, sus
 * horas a mano o anularlo. Las horas siguen saliendo de la puerta; lo que se ajusta a
 * mano dice quién y por qué, y no lo pisa el reloj.
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

    $this->correcciones = app(AttendanceCorrectionService::class);
});

/** Hora de la planta convertida al instante real. */
function enHoraDeLaPlanta(string $cuando): Carbon
{
    return Carbon::parse($cuando, 'America/Bogota')->utc();
}

/** Un turno por la puerta, y el día que de él calcula el reloj. */
function turnoPorLaPuerta(EmployeeQrCode $carne, string $entrada, string $salida): AttendanceDay
{
    app(AttendanceService::class)->record($carne, at: enHoraDeLaPlanta($entrada));
    app(AttendanceService::class)->record($carne, at: enHoraDeLaPlanta($salida));

    $fecha = Carbon::parse($entrada)->toDateString();
    app(AttendanceDayBuilder::class)->buildForEmployee($carne->employee, Carbon::parse($fecha), Carbon::parse($fecha));

    return AttendanceDay::query()->forTenant($carne->employee->tenant_id)
        ->where('employee_id', $carne->employee_id)
        ->whereDate('work_date', $fecha)
        ->sole();
}

// ── Qué marcas son de un día ───────────────────────────────────────────────────

it('takes the night shift exit as part of the day it started', function (): void {
    $noche = turnoPorLaPuerta($this->card, '2026-08-10 22:00', '2026-08-11 06:00');
    $tarde = turnoPorLaPuerta($this->card, '2026-08-11 14:00', '2026-08-11 22:00');

    $horas = fn (AttendanceDay $dia): array => $this->correcciones->marksOfDay($dia)
        ->map(fn (AttendanceScan $m): string => $m->scanned_at->copy()->setTimezone('America/Bogota')->format('d H:i'))
        ->all();

    expect($horas($noche))->toBe(['10 22:00', '11 06:00'])
        ->and($horas($tarde))->toBe(['11 14:00', '11 22:00']);
});

// ── Editar marcas ──────────────────────────────────────────────────────────────

it('corrects the time of a mark and recalculates the day', function (): void {
    $dia = turnoPorLaPuerta($this->card, '2026-08-10 06:00', '2026-08-10 14:00');
    $salida = $this->correcciones->marksOfDay($dia)->last();

    $dia = $this->correcciones->editDayMarks($dia, [
        $salida->id => enHoraDeLaPlanta('2026-08-10 16:00')->toDateTimeString(),
    ], 'El reloj de la puerta estaba atrasado', $this->rrhh);

    $vieja = AttendanceScan::query()->withoutGlobalScope(AttendanceScan::VALID_SCOPE)->find($salida->id);
    $nueva = AttendanceScan::query()->where('employee_id', $this->employee->id)->where('source', 'manual')->sole();

    expect((float) $dia->worked_hours)->toBe(10.0)
        ->and($vieja->isVoided())->toBeTrue()
        ->and($vieja->void_reason)->toContain('El reloj de la puerta estaba atrasado')
        ->and($nueva->recorded_by)->toBe($this->rrhh->id)
        ->and($nueva->notes)->toContain('Corrige la marca de las');
});

it('says so when no time was changed', function (): void {
    $dia = turnoPorLaPuerta($this->card, '2026-08-10 06:00', '2026-08-10 14:00');
    $salida = $this->correcciones->marksOfDay($dia)->last();

    expect(fn () => $this->correcciones->editDayMarks($dia, [
        $salida->id => $salida->scanned_at->toDateTimeString(),
    ], 'Sin cambios', $this->rrhh))->toThrow(AttendanceException::class, 'No cambió ninguna hora');
});

// ── Ajustar horas a mano ───────────────────────────────────────────────────────

it('adjusts the hours by hand, and the clock does not overwrite them', function (): void {
    $dia = turnoPorLaPuerta($this->card, '2026-08-10 06:00', '2026-08-10 16:00');

    $dia = $this->correcciones->adjustHours($dia, [
        'ordinary_hours' => 8, 'overtime_day_hours' => 0,
    ], 'Las dos extras no estaban autorizadas', $this->rrhh);

    expect($dia->isManuallyAdjusted())->toBeTrue()
        ->and((float) $dia->worked_hours)->toBe(8.0)
        ->and($dia->adjusted_by)->toBe($this->rrhh->id)
        ->and($dia->adjustment_reason)->toBe('Las dos extras no estaban autorizadas');

    // La siguiente reconstrucción no lo pisa.
    app(AttendanceDayBuilder::class)->buildForEmployee($this->employee, Carbon::parse('2026-08-10'), Carbon::parse('2026-08-10'));

    expect((float) $dia->refresh()->worked_hours)->toBe(8.0)
        ->and($dia->isManuallyAdjusted())->toBeTrue();
});

it('does not take more than 24 hours in a day', function (): void {
    $dia = turnoPorLaPuerta($this->card, '2026-08-10 06:00', '2026-08-10 14:00');

    expect(fn () => $this->correcciones->adjustHours($dia, ['ordinary_hours' => 20, 'overtime_day_hours' => 6], 'Demasiadas', $this->rrhh))
        ->toThrow(AttendanceException::class, 'de 0 a 24');
});

it('goes back to the clock when the marks of an adjusted day are corrected', function (): void {
    $dia = turnoPorLaPuerta($this->card, '2026-08-10 06:00', '2026-08-10 14:00');
    $this->correcciones->adjustHours($dia, ['ordinary_hours' => 4], 'Medio turno', $this->rrhh);
    $salida = $this->correcciones->marksOfDay($dia->refresh())->last();

    $dia = $this->correcciones->editDayMarks($dia, [
        $salida->id => enHoraDeLaPlanta('2026-08-10 15:00')->toDateTimeString(),
    ], 'La salida real fue a las 3', $this->rrhh);

    expect($dia->isManuallyAdjusted())->toBeFalse()
        ->and($dia->adjustment_reason)->toBeNull()
        ->and((float) $dia->worked_hours)->toBe(9.0);
});

// ── Anular el día ──────────────────────────────────────────────────────────────

it('voids a day: its marks are voided and it does not come back', function (): void {
    $dia = turnoPorLaPuerta($this->card, '2026-08-10 06:00', '2026-08-10 14:00');

    $this->correcciones->voidDay($dia, 'Marcó un carné que no era el suyo', $this->rrhh);

    $marcas = AttendanceScan::query()->withoutGlobalScope(AttendanceScan::VALID_SCOPE)->where('employee_id', $this->employee->id)->get();

    expect(AttendanceDay::query()->forTenant($this->tenant->id)->count())->toBe(0)
        ->and($marcas)->toHaveCount(2)
        ->and($marcas->every(fn (AttendanceScan $m): bool => $m->isVoided()))->toBeTrue()
        ->and($marcas->first()->void_reason)->toBe('Día anulado: Marcó un carné que no era el suyo');

    app(AttendanceDayBuilder::class)->buildForEmployee($this->employee, Carbon::parse('2026-08-10'), Carbon::parse('2026-08-10'));

    expect(AttendanceDay::query()->forTenant($this->tenant->id)->count())->toBe(0);
});

it('adjusts a confirmed day for a late novelty and keeps it confirmed, but not its gate marks', function (): void {
    $dia = turnoPorLaPuerta($this->card, '2026-08-10 06:00', '2026-08-10 14:00');
    app(AttendanceDayConfirmer::class)->confirm($dia, $this->rrhh);

    $dia = $this->correcciones->adjustHours($dia->refresh(), ['ordinary_hours' => 4], 'Medio turno: permiso en la tarde', $this->rrhh);

    expect($dia->status)->toBe(AttendanceDayStatus::Confirmada)
        ->and((float) $dia->worked_hours)->toBe(4.0)
        ->and($dia->adjusted_by)->toBe($this->rrhh->id)
        ->and($dia->adjustment_reason)->toBe('Medio turno: permiso en la tarde')
        // Cambiar la hora de sus marcas sí pide reabrirlo: el reloj no pisa lo firmado.
        ->and(fn () => $this->correcciones->editDayMarks($dia, [], 'Otra hora', $this->rrhh))
        ->toThrow(AttendanceException::class, 'reábrelo');
});

it('does not touch a day already paid in a closed payroll', function (): void {
    $dia = turnoPorLaPuerta($this->card, '2026-08-10 06:00', '2026-08-10 14:00');
    app(AttendanceDayConfirmer::class)->confirm($dia, $this->rrhh);
    PayrollRun::factory()->forPeriod('2026-08-01', '2026-08-30', 'Agosto 2026')->closed()->create(['tenant_id' => $this->tenant->id]);

    expect(fn () => $this->correcciones->adjustHours($dia->refresh(), ['ordinary_hours' => 4], 'Medio turno', $this->rrhh))
        ->toThrow(AttendanceException::class, 'cerrada')
        ->and(fn () => $this->correcciones->voidDay($dia->refresh(), 'No vino', $this->rrhh))
        ->toThrow(AttendanceException::class, 'cerrada')
        ->and((float) $dia->refresh()->worked_hours)->toBe(8.0);
});

// ── La pantalla ────────────────────────────────────────────────────────────────

it('lets human resources edit, adjust and void a day from «Horas por confirmar»', function (): void {
    $dia = turnoPorLaPuerta($this->card, '2026-08-10 06:00', '2026-08-10 14:00');
    $salida = $this->correcciones->marksOfDay($dia)->last();

    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $pantalla = Livewire::test(ListAttendanceDays::class);

    // El selector trabaja en la hora de la planta, como la escribe talento humano, y la
    // guarda en UTC al enviar.
    $pantalla->callAction(TestAction::make('editarMarcas')->table($dia), [
        'marcas' => [$salida->id => '2026-08-10 15:00:00'],
        'reason' => 'Salió a las 3',
    ])->assertNotified('Marcas corregidas');

    expect((float) $dia->refresh()->worked_hours)->toBe(9.0);

    $pantalla->callAction(TestAction::make('ajustarHoras')->table($dia), [
        'ordinary_hours' => 8, 'night_surcharge_hours' => 0, 'sunday_surcharge_hours' => 0,
        'night_sunday_surcharge_hours' => 0, 'overtime_day_hours' => 0, 'overtime_night_hours' => 0,
        'overtime_sunday_day_hours' => 0, 'overtime_sunday_night_hours' => 0,
        'reason' => 'La hora extra no se autorizó',
    ])->assertNotified('Horas ajustadas a mano');

    expect($dia->refresh()->isManuallyAdjusted())->toBeTrue();

    $pantalla->callAction(TestAction::make('anularDia')->table($dia), ['reason' => 'No trabajó ese día'])
        ->assertNotified('Día anulado');

    expect(AttendanceDay::query()->forTenant($this->tenant->id)->count())->toBe(0);
});

it('does not let the gate correct days', function (): void {
    $dia = turnoPorLaPuerta($this->card, '2026-08-10 06:00', '2026-08-10 14:00');

    $vigilante = User::factory()->create(['is_active' => true]);
    $vigilante->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $vigilante->assignRole('porteria');

    expect($vigilante->can('correct', $dia))->toBeFalse()
        ->and($this->rrhh->can('correct', $dia))->toBeTrue();
});
