<?php

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\NoveltyType;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\HumanResourcesDashboard;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Pages\FactorDeHoras;
use App\Filament\Pages\TableroTalentoHumano;
use App\Filament\Widgets\HumanResources\NoveltiesByTypeChart;
use App\Filament\Widgets\HumanResources\OvertimeByGroupChart;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Livewire\HumanResources\HoursFactorIndicator;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeNovelty;
use App\Models\EmployeeQrCode;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * El tablero de Talento Humano: lo que requiere atención hoy, el corte de horas en curso
 * y los accesos rápidos. Cada número lleva a la lista que lo resuelve.
 *
 * Hoy es el lunes 12 de octubre de 2026 a las 10 a. m.; corte el 26, así que el corte en
 * curso va del 27 de septiembre al 26 de octubre.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);
    app(PayrollParameterService::class)->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));
    app(PayrollParameterService::class)->setValue(PayrollParameter::HoursCutoffDay, 26, Carbon::parse('2026-01-01'), $this->tenant->id);

    $this->travelTo(Carbon::parse('2026-10-12 10:00', 'America/Bogota'));

    $this->rrhh = User::factory()->create(['is_active' => true]);
    $this->rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->rrhh->assignRole('talento-humano');

    $this->marcar = fn (EmployeeQrCode $carne, string $hora) => app(AttendanceService::class)
        ->record($carne, at: Carbon::parse($hora, 'America/Bogota')->utc());
});

it('counts what needs attention today', function (): void {
    // Con carné y salario: entró hoy y sigue adentro.
    $adentro = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'base_salary' => 2_000_000]);
    ($this->marcar)(EmployeeQrCode::factory()->forEmployee($adentro)->create(), '2026-10-12 07:00');

    // Entró el 5 y nadie marcó su salida.
    $olvido = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'base_salary' => 2_000_000]);
    ($this->marcar)(EmployeeQrCode::factory()->forEmployee($olvido)->create(), '2026-10-05 06:00');

    // Sin salario, sin carné y con el contrato fijo venciendo.
    Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'base_salary' => 0,
        'contract_type' => 'fijo',
        'contract_end_date' => '2026-10-31',
    ]);

    $atencion = app(HumanResourcesDashboard::class)->attention($this->tenant);

    expect($atencion['inside'])->toBe(1)
        ->and($atencion['markedToday'])->toBe(1)
        ->and($atencion['openEntries'])->toBe(1)
        ->and($atencion['withoutSalary'])->toBe(1)
        ->and($atencion['withoutBadge'])->toBe(1)
        ->and($atencion['contractsEnding'])->toBe(1);
});

it('follows the current cutoff: days left, confirmed share and overtime against the previous one', function (): void {
    $empleado = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'base_salary' => 2_100_000]);
    $carne = EmployeeQrCode::factory()->forEmployee($empleado)->create();

    // Dos días de este corte, de 6 a 6: cuatro o cinco horas extras cada uno.
    ($this->marcar)($carne, '2026-10-05 06:00');
    ($this->marcar)($carne, '2026-10-05 18:00');
    ($this->marcar)($carne, '2026-10-06 06:00');
    ($this->marcar)($carne, '2026-10-06 18:00');
    app(AttendanceDayBuilder::class)->buildForEmployee($empleado, Carbon::parse('2026-10-05'), Carbon::parse('2026-10-06'));
    AttendanceDay::query()->whereDate('work_date', '2026-10-05')->update(['status' => AttendanceDayStatus::Confirmada]);

    $corte = app(HumanResourcesDashboard::class)->currentCutoff($this->tenant);

    expect($corte['from']->toDateString())->toBe('2026-09-27')
        ->and($corte['to']->toDateString())->toBe('2026-10-26')
        ->and($corte['daysLeft'])->toBe(14)
        ->and($corte['confirmed'])->toBe(1)
        ->and($corte['proposed'])->toBe(1)
        ->and($corte['confirmedPercent'])->toBe(50)
        ->and($corte['overtime'])->toBeGreaterThan(0.0)
        ->and($corte['previousOvertime'])->toBe(0.0);
});

it('opens for human resources with its cards, shortcuts and charts', function (): void {
    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    EmployeeNovelty::query()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => Employee::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'type' => NoveltyType::Vacaciones,
        'starts_on' => '2026-10-05',
        'ends_on' => '2026-10-09',
    ]);

    Livewire::test(TableroTalentoHumano::class)
        ->assertOk()
        ->assertSee('Entradas sin salida')
        ->assertSee('Días por confirmar')
        ->assertSee('Horas extras del corte')
        ->assertSee('Faltan 14 días para el corte')
        ->assertSee('Accesos rápidos')
        ->assertSee('Revisar semana')
        ->assertSee('Nuevo trabajador');

    Livewire::test(OvertimeByGroupChart::class)->assertOk();
    Livewire::test(NoveltiesByTypeChart::class)->assertOk();

    expect(app(HumanResourcesDashboard::class)->noveltiesByType($this->tenant))->toBe(['Vacaciones' => 5]);
});

it('shows the hours factor indicator of human resources for what goes of the cutoff', function (): void {
    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    // Hora de 10.000 (2.100.000 / 210): dos horas extras diurnas son 25.000.
    app(PayrollParameterService::class)->setValue(PayrollParameter::MonthlyHoursDivisor, 210, Carbon::parse('2026-01-01'), $this->tenant->id);
    $operario = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'area_specific' => 'procesos', 'base_salary' => 2_100_000, 'hire_date' => '2025-01-01']);
    AttendanceDay::factory()->forEmployee($operario)->confirmed()->create([
        'work_date' => '2026-10-05',
        'ordinary_hours' => 7,
        'overtime_day_hours' => 2,
        'worked_hours' => 9,
    ]);

    // La página no espera el cálculo: muestra la silueta y el indicador llega después.
    Livewire::test(TableroTalentoHumano::class)
        ->assertOk()
        ->assertSee('Calculando lo que va del corte');

    Livewire::withoutLazyLoading()->test(HoursFactorIndicator::class, ['tenantId' => $this->tenant->id])
        ->assertSee('Indicador factor de horas · corte al 12/10/2026')
        ->assertSee('Calculado a las')
        ->assertSee('Horas ordinarias pagadas a producción')
        ->assertSee('$ 2.100.000')
        ->assertSee('Horas con recargos (horas extras) a personal de producción')
        ->assertSee('Total nómina de personal de producción y mantenimiento')
        ->assertSee('$ 2.125.000')
        ->assertSee('Total horas extras de producción y mantenimiento sin recargos')
        ->assertSee('$ 25.000');
});

it('keeps the indicator for a few minutes before recalculating it', function (): void {
    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $operario = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'area_specific' => 'procesos', 'base_salary' => 2_100_000, 'hire_date' => '2025-01-01']);
    $dia = fn (string $fecha) => AttendanceDay::factory()->forEmployee($operario)->confirmed()->create(['work_date' => $fecha, 'ordinary_hours' => 7, 'overtime_day_hours' => 2, 'worked_hours' => 9]);
    $extras = fn (): float => FactorDeHoras::dashboardIndicator($this->tenant)['overtimeTotal']['hours'];

    $dia('2026-10-05');
    expect($extras())->toBe(2.0);

    // Otro día con extras: dentro de los cinco minutos sigue lo calculado.
    $dia('2026-10-06');
    expect($extras())->toBe(2.0);

    $this->travel(FactorDeHoras::DASHBOARD_INDICATOR_MINUTES + 1)->minutes();
    expect($extras())->toBe(4.0);
});

it('shows a thin loading bar when opening another page of the panel', function (): void {
    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::getPanel('admin')->boot();

    expect((string) FilamentView::renderHook(PanelsRenderHook::BODY_END))
        ->toContain('fronda-barra-carga')
        ->toContain('prefers-reduced-motion');
});

it('is not for the maintenance administrator or the gate', function (): void {
    foreach (['administrador-general', 'porteria'] as $rol) {
        $usuario = User::factory()->create(['is_active' => true]);
        $usuario->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
        $usuario->assignRole($rol);
        $this->actingAs($usuario);

        expect(TableroTalentoHumano::canAccess())->toBeFalse();
    }
});
