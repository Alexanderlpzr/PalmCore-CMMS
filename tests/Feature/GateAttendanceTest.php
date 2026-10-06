<?php

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Exceptions\AttendanceException;
use App\Domain\HumanResources\Services\AttendanceCorrectionService;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Domain\HumanResources\Services\AttendanceDayConfirmer;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Resources\AttendanceScans\Pages\ListAttendanceScans;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Jobs\BuildAttendanceDaysJob;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * La puerta de punta a punta: el vigilante que entra a la app y marca, las horas que
 * salen de esas marcas en la hora de la planta, y talento humano corrigiendo la salida
 * que alguien olvidó.
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
});

/** Una persona de la empresa con un rol, con contraseña conocida. */
function personaConRol(Tenant $tenant, string $role): User
{
    $user = User::factory()->create(['is_active' => true, 'password' => 'Clave-De-Puerta-1']);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    return $user;
}

/** Hora de la planta convertida al instante real. */
function horaDePlanta(string $cuando): Carbon
{
    return Carbon::parse($cuando, 'America/Bogota')->utc();
}

function diaDe(Employee $employee, string $fecha): ?AttendanceDay
{
    return AttendanceDay::query()->forTenant($employee->tenant_id)
        ->where('employee_id', $employee->id)
        ->whereDate('work_date', $fecha)
        ->first();
}

// ── La app del vigilante ───────────────────────────────────────────────────────

it('lets a guard who signs in to the app scan badges', function (): void {
    // La app pedía al entrar solo habilidades de mantenimiento: cada marca daba 403.
    $vigilante = personaConRol($this->tenant, 'porteria');

    $token = $this->postJson('/api/v1/tokens', [
        'email' => $vigilante->email,
        'password' => 'Clave-De-Puerta-1',
        'tenant_slug' => $this->tenant->slug,
        'token_name' => 'Fronda Mobile',
    ])->assertCreated()
        ->assertJsonPath('user.modes', ['maintenance' => false, 'gate' => true])
        ->json('token');

    // Cada petición de la app llega sola, con su token: sin esto la prueba reusaría el
    // usuario que dejó autenticado el inicio de sesión.
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/attendance/scan', ['qr_token' => $this->card->qr_token], [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ])->assertCreated();
});

it('tells the app that a maintenance admin does not work the gate', function (): void {
    $admin = personaConRol($this->tenant, 'administrador-general');

    $this->postJson('/api/v1/tokens', [
        'email' => $admin->email,
        'password' => 'Clave-De-Puerta-1',
        'tenant_slug' => $this->tenant->slug,
        'token_name' => 'Fronda Mobile',
    ])->assertCreated()
        ->assertJsonPath('user.modes.maintenance', true)
        ->assertJsonPath('user.modes.gate', false);
});

it('lists the marks of the plant day, not of the UTC day', function (): void {
    // A las ocho de la noche en Colombia ya es el día siguiente en UTC.
    $vigilante = personaConRol($this->tenant, 'porteria');
    $token = $vigilante->createToken('app', ['attendance.read']);
    $token->accessToken->forceFill(['tenant_id' => $this->tenant->id])->save();

    app(AttendanceService::class)->record($this->card, at: horaDePlanta('2026-08-10 20:00'));

    $this->getJson('/api/v1/attendance/scans?date=2026-08-10', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
        'Accept' => 'application/json',
    ])->assertOk()->assertJsonCount(1, 'data');
});

// ── Las horas, en la hora de la planta ─────────────────────────────────────────

it('counts night hours in the plant time, not in UTC', function (): void {
    // La aplicación guarda en UTC: sin convertir, la ventana nocturna caía cinco horas
    // antes y la tarde entera salía como recargo nocturno.
    $inicioNoche = (float) app(PayrollParameterService::class)
        ->valueOn(PayrollParameter::NightWindowStart, Carbon::parse('2026-08-10'), $this->tenant->id);

    app(AttendanceService::class)->record($this->card, at: horaDePlanta('2026-08-10 14:00'));
    app(AttendanceService::class)->record($this->card, at: horaDePlanta('2026-08-10 22:00'));
    (new BuildAttendanceDaysJob($this->employee->id, '2026-08-10', '2026-08-10'))->handle(app(AttendanceDayBuilder::class));

    $dia = diaDe($this->employee, '2026-08-10');

    expect((float) $dia->worked_hours)->toBe(8.0)
        ->and((float) $dia->night_surcharge_hours)->toBe(max(0.0, 22 - $inicioNoche));
});

it('calculates the day on its own when the exit is scanned', function (): void {
    Queue::fake();

    app(AttendanceService::class)->record($this->card, at: horaDePlanta('2026-08-10 22:00'));
    app(AttendanceService::class)->record($this->card, at: horaDePlanta('2026-08-11 06:00'));

    // El turno de noche es del día en que arrancó, en la hora de la planta.
    Queue::assertPushed(BuildAttendanceDaysJob::class, fn (BuildAttendanceDaysJob $job): bool => $job->employeeId === $this->employee->id
        && $job->from === '2026-08-10'
        && $job->to === '2026-08-11');
});

// ── Correcciones de talento humano ─────────────────────────────────────────────

it('adds the forgotten exit and the day gets its hours', function (): void {
    $rrhh = personaConRol($this->tenant, 'talento-humano');
    app(AttendanceService::class)->record($this->card, at: horaDePlanta('2026-08-10 06:00'));

    $marca = app(AttendanceCorrectionService::class)->addManualMark(
        $this->employee,
        horaDePlanta('2026-08-10 14:00'),
        AttendanceDirection::Salida,
        'Salió por la puerta de carga sin marcar',
        $rrhh,
    );

    expect($marca->source)->toBe('manual')
        ->and($marca->recorded_by)->toBe($rrhh->id)
        ->and($marca->notes)->toBe('Salió por la puerta de carga sin marcar')
        ->and((float) diaDe($this->employee, '2026-08-10')->worked_hours)->toBe(8.0);
});

it('voids a wrong mark without deleting it, and the day loses its hours', function (): void {
    $rrhh = personaConRol($this->tenant, 'talento-humano');
    app(AttendanceService::class)->record($this->card, at: horaDePlanta('2026-08-10 06:00'));
    $salida = app(AttendanceService::class)->record($this->card, at: horaDePlanta('2026-08-10 14:00'));
    app(AttendanceCorrectionService::class)->addManualMark($this->employee, horaDePlanta('2026-08-10 14:05'), AttendanceDirection::Entrada, 'Prueba de anulación', $rrhh);

    app(AttendanceCorrectionService::class)->voidMark($salida, 'Pasaron el carné de otro trabajador', $rrhh);

    $anulada = AttendanceScan::query()->withoutGlobalScope(AttendanceScan::VALID_SCOPE)->find($salida->id);

    expect($anulada->isVoided())->toBeTrue()
        ->and($anulada->voided_by)->toBe($rrhh->id)
        ->and($anulada->void_reason)->toBe('Pasaron el carné de otro trabajador')
        ->and(AttendanceScan::query()->find($salida->id))->toBeNull()
        ->and((float) diaDe($this->employee, '2026-08-10')->worked_hours)->toBe(0.0);
});

it('removes the proposed day when its only mark is voided', function (): void {
    $rrhh = personaConRol($this->tenant, 'talento-humano');
    $entrada = app(AttendanceService::class)->record($this->card, at: horaDePlanta('2026-08-10 06:00'));
    app(AttendanceCorrectionService::class)->addManualMark($this->employee, horaDePlanta('2026-08-10 14:00'), AttendanceDirection::Salida, 'Cierre del turno', $rrhh);

    app(AttendanceCorrectionService::class)->voidMark($entrada, 'El trabajador estaba de vacaciones', $rrhh);
    app(AttendanceCorrectionService::class)->voidMark(
        AttendanceScan::query()->where('employee_id', $this->employee->id)->sole(),
        'Sin entrada, la salida tampoco vale',
        $rrhh,
    );

    expect(diaDe($this->employee, '2026-08-10'))->toBeNull();
});

it('does not correct a day someone already confirmed', function (): void {
    $rrhh = personaConRol($this->tenant, 'talento-humano');
    app(AttendanceService::class)->record($this->card, at: horaDePlanta('2026-08-10 06:00'));
    app(AttendanceCorrectionService::class)->addManualMark($this->employee, horaDePlanta('2026-08-10 14:00'), AttendanceDirection::Salida, 'Cierre del turno', $rrhh);
    app(AttendanceDayConfirmer::class)->confirm(diaDe($this->employee, '2026-08-10'), $rrhh);

    expect(fn () => app(AttendanceCorrectionService::class)->addManualMark(
        $this->employee,
        horaDePlanta('2026-08-10 15:00'),
        AttendanceDirection::Entrada,
        'Otra marca',
        $rrhh,
    ))->toThrow(AttendanceException::class, 'reábrelo');

    expect(diaDe($this->employee, '2026-08-10')->status)->toBe(AttendanceDayStatus::Confirmada);
});

// ── El panel de talento humano ─────────────────────────────────────────────────

it('shows the gate marks to HR and lets them add and void one', function (): void {
    $rrhh = personaConRol($this->tenant, 'talento-humano');
    $this->actingAs($rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $marca = app(AttendanceService::class)->record($this->card, at: horaDePlanta('2026-08-10 06:00'));

    Livewire::test(ListAttendanceScans::class)
        ->removeTableFilter('fecha')
        ->assertCanSeeTableRecords([$marca])
        ->callAction(TestAction::make('agregarMarca'), [
            'employee_id' => $this->employee->id,
            // El selector trabaja en la hora de la planta, como la escribe talento humano.
            'scanned_at' => '2026-08-10 14:00:00',
            'direction' => AttendanceDirection::Salida->value,
            'reason' => 'Olvidó marcar la salida',
        ])
        ->assertNotified('Marca agregada')
        ->callAction(TestAction::make('anular')->table($marca), ['reason' => 'Carné equivocado'])
        ->assertNotified('Marca anulada')
        ->assertCanNotSeeTableRecords([$marca]);

    $manual = AttendanceScan::query()->where('source', 'manual')->sole();

    // Las 2 p. m. de la planta, guardadas en UTC.
    expect($manual->scanned_at->equalTo(horaDePlanta('2026-08-10 14:00')))->toBeTrue();
});

it('lets HR download the QR of a worker', function (): void {
    $rrhh = personaConRol($this->tenant, 'talento-humano');
    $this->actingAs($rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(ListEmployees::class)
        ->callAction(TestAction::make('descargarQr')->table($this->employee))
        ->assertFileDownloaded();
});
