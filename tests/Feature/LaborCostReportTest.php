<?php

use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Domain\HumanResources\Enums\NoveltyType;
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
use App\Models\EmployeeNovelty;
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
 * El tablero «Costo de personal»: jornal (salario ÷ 30) por cada día contratado, entre o no
 * —el costo fijo, como lo liquida la nómina; pedido el 2026-10-23—;
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

it('charges the fixed daily wage of everyone hired, before anybody comes in', function (): void {
    Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'base_salary' => 2_000_000,
        'status' => EmploymentStatus::Retirado,
        'termination_date' => '2026-09-01',
    ]);

    $informe = app(LaborCostReport::class)->forRange($this->tenant, Carbon::parse('2026-10-13'), Carbon::parse('2026-10-13'));

    // Dos en nómina —el retirado no cuenta—, ninguno entró: el día ya cuesta sus jornales,
    // 3.000.000 ÷ 30 de Ana y 1.500.000 ÷ 30 de Beto.
    expect($informe['totals']['people'])->toBe(2)
        ->and($informe['totals']['present'])->toBe(0)
        ->and($informe['totals']['jornal'])->toEqual(150_000.0)
        ->and($informe['totals']['total'])->toEqual(150_000.0)
        ->and($informe['rows'])->toHaveCount(2)
        ->and($informe['areas'])->toHaveKeys(['procesos', 'mantenimiento'])
        ->and($informe['entries'])->toBe([]);
});

it('lists who comes in without changing the fixed cost', function (): void {
    app(AttendanceService::class)->record($this->carneAna, at: Carbon::parse('2026-10-14 06:10', 'America/Bogota')->utc());

    $informe = app(LaborCostReport::class)->forRange($this->tenant, Carbon::parse('2026-10-14'), Carbon::parse('2026-10-14'));

    // Ana entró y Beto no ha llegado: los dos jornales cuentan igual. La entrada es asistencia
    // y lleva el valor del día de Ana (3.000.000 ÷ 30).
    expect($informe['totals']['present'])->toBe(1)
        ->and($informe['totals']['jornal'])->toEqual(150_000.0)
        ->and($informe['areas'])->toHaveKeys(['procesos', 'mantenimiento'])
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

it('accumulates the cutoff from the 27th, one daily wage per day hired, came in or not', function (): void {
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
        // Los 18 días del 27/09 al 14/10, aunque solo entró tres.
        ->and(filaDe($informe, $this->ana)['jornal'])->toEqual(1_800_000.0)
        ->and(filaDe($informe, $this->beto)['days'])->toBe(5)
        ->and(filaDe($informe, $this->beto)['present'])->toBe(1)
        // Desde que lo contrataron, el 10/10: cinco días.
        ->and(filaDe($informe, $this->beto)['jornal'])->toEqual(250_000.0);
});

it('shows the dashboard to human resources, by day and by cutoff', function (): void {
    turnoDeCosto($this->carneAna, '2026-10-13 06:00', '2026-10-13 20:00');

    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(CostoDePersonal::class, ['fecha' => '2026-10-13'])
        ->assertOk()
        ->assertSee('Costo por área')
        // Las entradas y la tabla por trabajador se buscan por nombre.
        ->assertSee('Buscar persona o área')
        ->assertSee('Buscar trabajador, cargo o área')
        ->assertSeeHtml('data-buscar="ana')
        ->assertSee('Horas extras y recargos por área')
        ->assertSee('Alimentación por área')
        ->assertSee('Procesos')
        // Beto no entró el 13, pero su jornal cuenta: mantenimiento también costó.
        ->assertSee('Beto')
        ->assertSee('salario ÷ 30 por día contratado')
        ->set('vista', 'corte')
        ->assertOk()
        ->assertSee('Del 27/09 al 13/10')
        // El futuro no cuesta todavía: un día después de hoy vuelve a hoy.
        ->set('fecha', '2026-10-20')
        ->assertSet('fecha', '2026-10-14')
        // Hoy nadie ha entrado: el día ya cuesta sus jornales, por área, y se refresca solo.
        ->set('vista', 'dia')
        ->assertSee('Lo que cuesta hoy')
        ->assertDontSee('El día arranca en $ 0')
        ->assertSee('Todavía no ha entrado nadie')
        ->assertSee('En vivo')
        ->assertSee('Por área')
        ->assertSee('Mantenimiento');
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

it('charges the paid novelty days of whoever did not come, with what EPS or ARL give back', function (): void {
    // Beto: incapacidad por accidente de trabajo desde el 20 de septiembre, con prórroga.
    EmployeeNovelty::factory()->forEmployee($this->beto)->of(NoveltyType::IncapacidadAt, '2026-09-20', '2026-09-30')->create();
    EmployeeNovelty::factory()->forEmployee($this->beto)->of(NoveltyType::IncapacidadAt, '2026-10-01', '2026-11-05')->create();
    // Ana: enfermedad general del 1 al 5, pero el 5 vino a trabajar.
    EmployeeNovelty::factory()->forEmployee($this->ana)->of(NoveltyType::IncapacidadEgSalario, '2026-10-01', '2026-10-05')->create();
    turnoDeCosto($this->carneAna, '2026-10-05 06:00', '2026-10-05 13:00');
    // Caro: accidente el 3; se fue el 4 de permiso no remunerado.
    $caro = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'base_salary' => 2_400_000, 'hire_date' => '2025-01-01']);
    EmployeeNovelty::factory()->forEmployee($caro)->of(NoveltyType::IncapacidadAt, '2026-10-03', '2026-10-04')->create();
    EmployeeNovelty::factory()->forEmployee($caro)->of(NoveltyType::PermisoNoRemunerado, '2026-10-05', '2026-10-05')->create();

    $informe = app(LaborCostReport::class)->forRange($this->tenant, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-05'));
    $beto = filaDe($informe, $this->beto);
    $ana = filaDe($informe, $this->ana);
    $carolina = filaDe($informe, $caro);

    // Beto, 5 días a 50.000: la prórroga sigue la cuenta, así que la ARL los devuelve todos.
    expect($beto['novelties'])->toEqual(250_000.0)
        ->and($beto['novelty_days'])->toBe(5)
        ->and($beto['reimbursed'])->toEqual(250_000.0)
        ->and($beto['jornal'])->toEqual(0.0)
        // Ana, 4 días a 100.000 (el 5 es jornal); la EPS reconoce dos tercios desde el 3.º.
        ->and($ana['novelties'])->toEqual(400_000.0)
        ->and($ana['novelty_days'])->toBe(4)
        ->and($ana['reimbursed'])->toEqualWithDelta(133_333.33, 0.01)
        ->and($ana['jornal'])->toEqual(100_000.0)
        // Caro: el día del accidente lo pone la empresa; el permiso no remunerado no suma ni
        // es jornal. Sus jornales son el 1 y el 2.
        ->and($carolina['novelties'])->toEqual(160_000.0)
        ->and($carolina['jornal'])->toEqual(160_000.0)
        ->and($carolina['reimbursed'])->toEqual(80_000.0)
        ->and($informe['totals']['novelties'])->toEqual(810_000.0)
        ->and($informe['totals']['reimbursed'])->toEqualWithDelta(463_333.33, 0.01)
        ->and($informe['totals']['total'])->toEqual(1_070_000.0 + $ana['meals'] + $ana['surcharges'] + $ana['overtime'])
        ->and($informe['areas']['mantenimiento']['novelties'])->toEqual(250_000.0);

    // Las novedades son costo fijo: «solo extras y recargos» no las cuenta.
    $soloExtras = LaborCostReport::onlyConcepts($informe, LaborCostReport::SCOPES['extras']);

    expect($soloExtras['totals']['novelties'])->toEqual(0.0)
        ->and($soloExtras['totals']['reimbursed'])->toEqual(0.0)
        ->and(collect($soloExtras['rows'])->pluck('employee_id'))->not->toContain($this->beto->id);

    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    // Beto sigue incapacitado hoy: su día va en novedades pagadas.
    Livewire::test(CostoDePersonal::class)
        ->assertOk()
        ->assertSee('Novedades pagadas')
        ->assertSee('EPS/ARL reembolsan $ 50.000')
        ->assertSee('Beto')
        ->assertDontSee('El día arranca en $ 0');
});

it('pays maternity and paternity leave at salary, recognized in full by the EPS', function (): void {
    // Pedido 2026-10-24. Ana, licencia de maternidad; Beto, de paternidad: los días valen el
    // salario, la EPS los devuelve completos desde el primero y no son jornal.
    EmployeeNovelty::factory()->forEmployee($this->ana)->of(NoveltyType::LicenciaMaternidad, '2026-09-20', '2027-01-23')->create();
    EmployeeNovelty::factory()->forEmployee($this->beto)->of(NoveltyType::LicenciaPaternidad, '2026-10-01', '2026-10-14')->create();

    $informe = app(LaborCostReport::class)->forRange($this->tenant, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-05'));
    $ana = filaDe($informe, $this->ana);
    $beto = filaDe($informe, $this->beto);

    expect($ana['novelties'])->toEqual(500_000.0)
        ->and($ana['reimbursed'])->toEqual(500_000.0)
        ->and($ana['jornal'])->toEqual(0.0)
        ->and($beto['novelties'])->toEqual(250_000.0)
        ->and($beto['reimbursed'])->toEqual(250_000.0);

    // Para la nómina son tiempo de servicio: prima, cesantías y vacaciones.
    foreach ([NoveltyType::LicenciaMaternidad, NoveltyType::LicenciaPaternidad] as $licencia) {
        expect($licencia->isPaid())->toBeTrue()
            ->and($licencia->countsMonthlyEarnings())->toBeTrue()
            ->and($licencia->countsSeveranceBase())->toBeTrue()
            ->and($licencia->countsVacationBase())->toBeTrue()
            ->and($licencia->countsIbcPension())->toBeFalse();
    }

    expect(NoveltyType::options())->toHaveKeys(['licencia_maternidad', 'licencia_paternidad'])
        ->and(NoveltyType::LicenciaMaternidad->label())->toBe('Licencia de maternidad');
});

it('keeps the dashboard away from whoever cannot see salaries', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $admin->assignRole('administrador-general');

    $this->actingAs($admin);

    expect(CostoDePersonal::canAccess())->toBeFalse();
});
