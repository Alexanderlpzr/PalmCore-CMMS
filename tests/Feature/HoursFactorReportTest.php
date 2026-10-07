<?php

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\HoursFactorReport;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Domain\HumanResources\Services\PayrollRunService;
use App\Filament\Pages\FactorDeHoras;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * El indicador «factor de horas» (TH-INF-F-002) sale de la nómina liquidada. Cuenta las
 * horas pagadas como bonificación igual que las extras, separa recargos de extras y pasa
 * a «Apoyo a mantenimiento» los días de producción marcados así.
 *
 * Hora de 10.000 (2.100.000 / 210) para revisar de cabeza.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $parametros = app(PayrollParameterService::class);
    $parametros->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));
    $parametros->setValue(PayrollParameter::MonthlyHoursDivisor, 210, Carbon::parse('2026-01-01'), $this->tenant->id);
    $parametros->setValue(PayrollParameter::OvertimeExcessAsBonus, 1, Carbon::parse('2026-01-01'), $this->tenant->id);

    $this->run = PayrollRun::factory()
        ->forPeriod('2026-10-01', '2026-10-30', 'Nómina de octubre de 2026')
        ->create(['tenant_id' => $this->tenant->id, 'hours_from' => '2026-09-27', 'hours_to' => '2026-10-26']);

    $crear = fn (string $area): Employee => Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'area_specific' => $area,
        'base_salary' => 2_100_000,
        'hire_date' => '2025-01-01',
    ]);

    $this->operario = $crear('procesos');
    $this->mecanico = $crear('mantenimiento');
    $this->mope = $crear('mope');

    // Producción: cuatro extras diurnas (dos de horas extras, dos de bonificación) y un
    // turno de noche que hizo para mantenimiento.
    diaParaElIndicador($this->operario, '2026-10-05', ['ordinary_hours' => 7, 'overtime_day_hours' => 4]);
    diaParaElIndicador($this->operario, '2026-10-06', ['ordinary_hours' => 1, 'night_surcharge_hours' => 6], ['maintenance_support' => true]);

    // Mantenimiento: el domingo de descanso que vino a trabajar.
    diaParaElIndicador($this->mecanico, '2026-10-11', ['overtime_sunday_day_hours' => 8], ['rest_day_worked' => true]);

    app(PayrollRunService::class)->calculate($this->run);
});

function diaParaElIndicador(Employee $employee, string $fecha, array $horas, array $extra = []): AttendanceDay
{
    return AttendanceDay::factory()->forEmployee($employee)->confirmed()->create([
        'work_date' => $fecha,
        'ordinary_hours' => 0,
        'worked_hours' => array_sum($horas),
        ...$horas,
        ...$extra,
    ]);
}

it('adds up each group with what was paid as bonus', function (): void {
    $informe = app(HoursFactorReport::class)->forRun($this->run);
    $produccion = $informe['groups']['produccion'];

    // 2 h × 12.500 de horas extras + 2 h × 12.500 de bonificación.
    expect($produccion['workers'])->toBe(1)
        ->and($produccion['ordinary'])->toBe(2_100_000.0)
        ->and($produccion['overtime_hours'])->toBe(4.0)
        ->and($produccion['overtime_amount'])->toBe(50_000.0)
        ->and($produccion['factor'])->toBe(round(50_000 / 2_100_000, 4))
        ->and($informe['groups']['mantenimiento']['overtime_amount'])->toBe(164_000.0)
        ->and($informe['groups']['mope']['amount'])->toBe(0.0);
});

it('moves the production days marked as maintenance support to their own row', function (): void {
    $informe = app(HoursFactorReport::class)->forRun($this->run);

    expect($informe['groups']['produccion']['surcharge_hours'])->toBe(0.0)
        ->and($informe['groups']['apoyo_mantenimiento']['surcharge_hours'])->toBe(6.0)
        ->and($informe['groups']['apoyo_mantenimiento']['surcharge_amount'])->toBe(21_000.0)
        // Su sueldo se queda en producción: el apoyo no tiene factor propio.
        ->and($informe['groups']['apoyo_mantenimiento']['factor'])->toBeNull()
        ->and($informe['total']['amount'])->toBe(235_000.0)
        ->and($informe['total']['workers'])->toBe(3);
});

it('compares against the previous payroll of the company', function (): void {
    $septiembre = PayrollRun::factory()
        ->forPeriod('2026-09-01', '2026-09-30', 'Nómina de septiembre de 2026')
        ->create(['tenant_id' => $this->tenant->id, 'calculated_at' => now()]);

    expect(app(HoursFactorReport::class)->previousRun($this->run)?->is($septiembre))->toBeTrue();
});

it('maps each area of the record to a group of the report', function (?string $area, string $grupo): void {
    expect(HoursFactorReport::groupOf($area))->toBe($grupo);
})->with([
    ['procesos', 'produccion'],
    ['laboratorio', 'produccion'],
    ['calidad', 'produccion'],
    ['mantenimiento', 'mantenimiento'],
    ['mope', 'mope'],
    ['administrativo', 'administrativo'],
    [null, 'sin_area'],
]);

it('shows the report to human resources', function (): void {
    $rrhh = User::factory()->create(['is_active' => true]);
    $rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $rrhh->assignRole('talento-humano');
    $this->actingAs($rrhh);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(FactorDeHoras::class)
        ->assertSet('runId', $this->run->id)
        ->assertSee('Producción')
        ->assertSee('Apoyo a mantenimiento')
        ->assertSee('Mantenimiento')
        ->assertSee('$ 235.000')
        ->assertSee('Horas del 27/09/2026 al 26/10/2026');
});
