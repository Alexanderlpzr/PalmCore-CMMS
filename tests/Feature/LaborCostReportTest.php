<?php

use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Domain\HumanResources\Services\AttendanceDayConfirmer;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\LaborCostReport;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Pages\CostoDePersonal;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * El tablero «Costo de personal»: jornal (salario ÷ 30) por cada día que la persona entró;
 * recargos y extras valorados como en la nómina, con lo sin confirmar como estimado; y
 * alimentación. Acumulado del corte del 27 al 26.
 *
 * Hoy es el miércoles 14 de octubre de 2026 a las 3 p. m.; el corte va del 27/09 al 26/10.
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
    $parametros->setValue(PayrollParameter::HoursCutoffDay, 26, Carbon::parse('2026-01-01'), $this->tenant->id);
    $parametros->setValue(PayrollParameter::MealAllowanceValue, 10_000, Carbon::parse('2026-09-27'), $this->tenant->id);
    $parametros->setValue(PayrollParameter::MealSnackValue, 7_000, Carbon::parse('2026-09-27'), $this->tenant->id);

    $this->travelTo(Carbon::parse('2026-10-14 15:00', 'America/Bogota'));

    $this->ana = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Ana',
        'base_salary' => 3_000_000,
        'area_specific' => 'procesos',
        'hire_date' => '2025-01-01',
    ]);
    $this->carneAna = EmployeeQrCode::factory()->forEmployee($this->ana)->create();

    $this->beto = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Beto',
        'base_salary' => 1_500_000,
        'area_specific' => 'mantenimiento',
        'hire_date' => '2025-01-01',
    ]);

    $this->rrhh = User::factory()->create(['is_active' => true]);
    $this->rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->rrhh->assignRole('talento-humano');
});

/** Un turno por la puerta, en hora de la planta, con su día armado y confirmado si se pide. */
function turnoDeCosto(EmployeeQrCode $carne, string $entrada, string $salida, bool $confirmar = true): AttendanceDay
{
    app(AttendanceService::class)->record($carne, at: Carbon::parse($entrada, 'America/Bogota')->utc());
    app(AttendanceService::class)->record($carne, at: Carbon::parse($salida, 'America/Bogota')->utc());

    $fecha = Carbon::parse($entrada)->toDateString();
    app(AttendanceDayBuilder::class)->buildForEmployee($carne->employee, Carbon::parse($fecha), Carbon::parse($fecha));
    $dia = AttendanceDay::query()->where('employee_id', $carne->employee_id)->whereDate('work_date', $fecha)->sole();

    if ($confirmar) {
        app(AttendanceDayConfirmer::class)->confirm($dia, User::factory()->create());
    }

    return $dia->fresh();
}

/** Lo que la nómina pagaría por los recargos y extras de ese día. */
function valorDeLasHoras(AttendanceDay $dia, float $salario, string $tenantId): float
{
    $parametros = app(PayrollParameterService::class);
    $valorHora = $salario / $parametros->valueOn(PayrollParameter::MonthlyHoursDivisor, $dia->work_date, $tenantId);

    return array_sum(array_map(
        fn (array $bolsa): float => $bolsa['hours'] * $valorHora * $parametros->valueOn($bolsa['parameter'], $dia->work_date, $tenantId),
        $dia->hours()->paidBuckets(),
    ));
}

function filaDe(array $informe, Employee $trabajador): array
{
    return collect($informe['rows'])->firstWhere('employee_id', $trabajador->id);
}

it('starts the day at zero while nobody has come in', function (): void {
    Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'base_salary' => 2_000_000,
        'status' => EmploymentStatus::Retirado,
        'termination_date' => '2026-09-01',
    ]);

    $informe = app(LaborCostReport::class)->forRange($this->tenant, Carbon::parse('2026-10-13'), Carbon::parse('2026-10-13'));

    // Dos en nómina —el retirado no cuenta—, ninguno entró: nada cuesta todavía.
    expect($informe['totals']['people'])->toBe(2)
        ->and($informe['totals']['present'])->toBe(0)
        ->and($informe['totals']['total'])->toEqual(0.0)
        ->and($informe['rows'])->toBe([])
        ->and($informe['areas'])->toBe([])
        ->and($informe['entries'])->toBe([]);
});

it('charges the daily wage only of whoever comes in, as they come in', function (): void {
    app(AttendanceService::class)->record($this->carneAna, at: Carbon::parse('2026-10-14 06:10', 'America/Bogota')->utc());

    $informe = app(LaborCostReport::class)->forRange($this->tenant, Carbon::parse('2026-10-14'), Carbon::parse('2026-10-14'));

    // Ana entró: su jornal (3.000.000 ÷ 30). Beto no ha llegado: no cuesta.
    expect($informe['totals']['present'])->toBe(1)
        ->and($informe['totals']['jornal'])->toEqual(100_000.0)
        ->and($informe['areas'])->toHaveKeys(['procesos'])
        ->and($informe['areas'])->not->toHaveKey('mantenimiento')
        ->and($informe['entries'])->toHaveCount(1)
        ->and($informe['entries'][0]['name'])->toBe($this->ana->fullName())
        ->and($informe['entries'][0]['at']->format('H:i'))->toBe('06:10')
        ->and($informe['entries'][0]['jornal'])->toEqual(100_000.0)
        ->and($informe['entries'][0]['inside'])->toBeTrue();
});

it('values the surcharges and overtime like the payroll and adds the meals', function (): void {
    $dia = turnoDeCosto($this->carneAna, '2026-10-13 06:00', '2026-10-13 20:00');
    $horas = valorDeLasHoras($dia, 3_000_000, $this->tenant->id);

    $informe = app(LaborCostReport::class)->forRange($this->tenant, Carbon::parse('2026-10-13'), Carbon::parse('2026-10-13'));
    $fila = filaDe($informe, $this->ana);

    expect($horas)->toBeGreaterThan(0)
        ->and($fila['present'])->toBe(1)
        ->and($fila['surcharges'] + $fila['overtime'])->toEqualWithDelta($horas, 0.05)
        ->and($fila['overtime_hours'])->toBeGreaterThan(0)
        // Desayuno, almuerzo y merienda.
        ->and($fila['meals'])->toEqual(27_000.0)
        ->and($fila['meal_count'])->toBe(3)
        ->and($fila['total'])->toEqualWithDelta(100_000 + $horas + 27_000, 0.05)
        ->and($fila['estimated'])->toBeFalse()
        ->and($informe['areas']['procesos']['meals'])->toEqual(27_000.0);
});

it('counts unconfirmed hours, marked as an estimate', function (): void {
    $dia = turnoDeCosto($this->carneAna, '2026-10-13 06:00', '2026-10-13 20:00', confirmar: false);

    $informe = app(LaborCostReport::class)->forRange($this->tenant, Carbon::parse('2026-10-13'), Carbon::parse('2026-10-13'));
    $fila = filaDe($informe, $this->ana);

    expect($fila['surcharges'] + $fila['overtime'])->toEqualWithDelta(valorDeLasHoras($dia, 3_000_000, $this->tenant->id), 0.05)
        ->and($fila['meals'])->toEqual(27_000.0)
        ->and($fila['estimated'])->toBeTrue()
        ->and($informe['estimated'])->toBeTrue();
});

it('counts whoever is still inside up to now', function (): void {
    app(AttendanceService::class)->record($this->carneAna, at: Carbon::parse('2026-10-14 05:00', 'America/Bogota')->utc());

    $informe = app(LaborCostReport::class)->forRange($this->tenant, Carbon::parse('2026-10-14'), Carbon::parse('2026-10-14'));
    $fila = filaDe($informe, $this->ana);

    // De 5 a. m. a 3 p. m. son 10 horas: hay extras, y ya cubrió el desayuno y el almuerzo.
    expect($fila['inside'])->toBeTrue()
        ->and($fila['present'])->toBe(1)
        ->and($fila['overtime_hours'])->toBeGreaterThan(0)
        ->and($fila['meals'])->toEqual(20_000.0)
        ->and($fila['estimated'])->toBeTrue()
        ->and($informe['totals']['inside'])->toBe(1);
});

it('accumulates the cutoff from the 27th, one daily wage per day each person came in', function (): void {
    $this->beto->update(['hire_date' => '2026-10-10']);
    $carneBeto = EmployeeQrCode::factory()->forEmployee($this->beto)->create();

    turnoDeCosto($this->carneAna, '2026-09-28 06:00', '2026-09-28 13:00');
    turnoDeCosto($this->carneAna, '2026-10-05 06:00', '2026-10-05 13:00');
    // Dos entradas el mismo día: un solo jornal.
    turnoDeCosto($this->carneAna, '2026-10-13 06:00', '2026-10-13 09:00');
    app(AttendanceService::class)->record($this->carneAna, at: Carbon::parse('2026-10-13 10:00', 'America/Bogota')->utc());
    app(AttendanceService::class)->record($this->carneAna, at: Carbon::parse('2026-10-13 13:00', 'America/Bogota')->utc());
    turnoDeCosto($carneBeto, '2026-10-12 06:00', '2026-10-12 13:00');

    $informe = app(LaborCostReport::class)->forRange($this->tenant, Carbon::parse('2026-09-27'), Carbon::parse('2026-10-14'));

    expect(filaDe($informe, $this->ana)['days'])->toBe(18)
        ->and(filaDe($informe, $this->ana)['present'])->toBe(3)
        ->and(filaDe($informe, $this->ana)['jornal'])->toEqual(300_000.0)
        ->and(filaDe($informe, $this->beto)['days'])->toBe(5)
        ->and(filaDe($informe, $this->beto)['present'])->toBe(1)
        ->and(filaDe($informe, $this->beto)['jornal'])->toEqual(50_000.0);
});

it('shows the dashboard to human resources, by day and by cutoff', function (): void {
    turnoDeCosto($this->carneAna, '2026-10-13 06:00', '2026-10-13 20:00');

    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(CostoDePersonal::class, ['fecha' => '2026-10-13'])
        ->assertOk()
        ->assertSee('Costo por área')
        ->assertSee('Horas extras y recargos por área')
        ->assertSee('Alimentación por área')
        ->assertSee('Procesos')
        // Beto no entró el 13: mantenimiento no costó nada ese día.
        ->assertDontSee('Beto')
        ->set('vista', 'corte')
        ->assertOk()
        ->assertSee('Del 27/09 al 13/10')
        // El futuro no cuesta todavía: un día después de hoy vuelve a hoy.
        ->set('fecha', '2026-10-20')
        ->assertSet('fecha', '2026-10-14')
        // Hoy nadie ha entrado: el día arranca en cero, sin tortas, y se refresca solo.
        ->set('vista', 'dia')
        ->assertSee('El día arranca en $ 0')
        ->assertSee('Todavía no ha entrado nadie')
        ->assertSee('En vivo')
        ->assertDontSee('Costo por área');
});

it('shows only overtime and surcharges when asked, without the fixed cost', function (): void {
    $dia = turnoDeCosto($this->carneAna, '2026-10-13 06:00', '2026-10-13 20:00');
    // Beto entra una jornada corta: tiene jornal pero ni extras ni recargos.
    turnoDeCosto(EmployeeQrCode::factory()->forEmployee($this->beto)->create(), '2026-10-13 06:00', '2026-10-13 12:00');
    $horas = valorDeLasHoras($dia, 3_000_000, $this->tenant->id);

    $completo = app(LaborCostReport::class)->forRange($this->tenant, Carbon::parse('2026-10-13'), Carbon::parse('2026-10-13'));
    $soloExtras = LaborCostReport::onlyConcepts($completo, LaborCostReport::SCOPES['extras']);

    expect($completo['rows'])->toHaveCount(2)
        ->and($soloExtras['rows'])->toHaveCount(1)
        ->and($soloExtras['rows'][0]['employee_id'])->toBe($this->ana->id)
        ->and($soloExtras['rows'][0]['jornal'])->toEqual(0.0)
        ->and($soloExtras['rows'][0]['meals'])->toEqual(0.0)
        ->and($soloExtras['totals']['total'])->toEqualWithDelta($horas, 0.05)
        ->and($soloExtras['totals']['jornal'])->toEqual(0.0)
        ->and($soloExtras['totals']['meals'])->toEqual(0.0)
        ->and($soloExtras['areas'])->toHaveKeys(['procesos'])
        ->and($soloExtras['areas'])->not->toHaveKey('mantenimiento')
        ->and($soloExtras['areas']['procesos']['total'])->toEqualWithDelta($horas, 0.05);

    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(CostoDePersonal::class, ['fecha' => '2026-10-13'])
        ->set('contar', 'extras')
        ->assertOk()
        ->assertSee('Solo extras y recargos')
        ->assertSee('Horas extras y recargos por área')
        ->assertSee('Recargos contra horas extras')
        ->assertDontSee('Alimentación por área')
        // En «solo extras» las entradas se listan sin el jornal.
        ->assertDontSee('+ $ 50.000')
        // Un valor raro en la dirección vuelve al costo completo.
        ->set('contar', 'cualquiera')
        ->assertSet('contar', 'todo');
});

it('keeps the dashboard away from whoever cannot see salaries', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $admin->assignRole('administrador-general');

    $this->actingAs($admin);

    expect(CostoDePersonal::canAccess())->toBeFalse();
});
