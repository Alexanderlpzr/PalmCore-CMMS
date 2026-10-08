<?php

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Domain\HumanResources\Services\AttendanceDayConfirmer;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\MealAllowanceCalculator;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Pages\Alimentacion;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
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
 * El auxilio de alimentación: 10.000 por cada comida cuya franja el trabajador cobija,
 * es decir, en la que estuvo trabajando en algún momento. Turno de día: desayuno 5:20–7:20,
 * almuerzo 11:20–1:20, merienda 5:30–6:30 p. m. Turno de noche (entra desde la 1 p. m.):
 * cena 5:30–6:30 p. m., merienda nocturna 8:30–9:30 p. m. y desayuno si sale de 5:30 a
 * 6:30 a. m. Solo días confirmados; se paga aparte de la nómina.
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
    $parametros->setValue(PayrollParameter::MealAllowanceValue, 10_000, Carbon::parse('2026-09-27'), $this->tenant->id);
    $parametros->setValue(PayrollParameter::MealSnackValue, 7_000, Carbon::parse('2026-09-27'), $this->tenant->id);
    $parametros->setValue(PayrollParameter::HoursCutoffDay, 26, Carbon::parse('2026-01-01'), $this->tenant->id);

    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Diego', 'last_name' => 'Medina']);
    $this->card = EmployeeQrCode::factory()->forEmployee($this->employee)->create();

    $this->rrhh = User::factory()->create(['is_active' => true]);
    $this->rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->rrhh->assignRole('talento-humano');
});

/** Un turno por la puerta, en hora de la planta; confirmado si se pide. */
function turnoConComida(EmployeeQrCode $carne, string $entrada, string $salida, bool $confirmar = true): AttendanceDay
{
    app(AttendanceService::class)->record($carne, at: Carbon::parse($entrada, 'America/Bogota')->utc());
    app(AttendanceService::class)->record($carne, at: Carbon::parse($salida, 'America/Bogota')->utc());

    $fecha = Carbon::parse($entrada)->toDateString();
    app(AttendanceDayBuilder::class)->buildForEmployee($carne->employee, Carbon::parse($fecha), Carbon::parse($fecha));
    $dia = AttendanceDay::query()->where('employee_id', $carne->employee_id)->whereDate('work_date', $fecha)->sole();

    if ($confirmar) {
        app(AttendanceDayConfirmer::class)->confirm($dia, User::factory()->create());
    }

    return $dia;
}

it('counts the meals whose window the shift touches', function (string $entrada, string $salida, array $comidas): void {
    $meals = app(MealAllowanceCalculator::class)->mealsOf(
        CarbonImmutable::parse($entrada, 'America/Bogota'),
        CarbonImmutable::parse($salida, 'America/Bogota'),
        $this->tenant->id,
    );

    expect($meals)->toBe($comidas);
})->with([
    'día completo' => ['2026-10-05 06:00', '2026-10-05 18:00', ['desayuno', 'almuerzo', 'merienda']],
    'entra tarde al desayuno' => ['2026-10-05 07:30', '2026-10-05 14:00', ['almuerzo']],
    'basta un rato de la franja' => ['2026-10-05 05:00', '2026-10-05 07:00', ['desayuno']],
    'noche completa' => ['2026-10-05 18:00', '2026-10-06 06:00', ['cena', 'merienda_nocturna', 'desayuno']],
    'noche que sale a la 1' => ['2026-10-05 18:00', '2026-10-06 01:00', ['cena', 'merienda_nocturna']],
    'noche que entra tarde' => ['2026-10-05 21:45', '2026-10-06 06:00', ['desayuno']],
    'tarde de 2 a 10' => ['2026-10-05 14:00', '2026-10-05 22:00', ['cena', 'merienda_nocturna']],
]);

it('pays only confirmed days, each meal once a day', function (): void {
    turnoConComida($this->card, '2026-10-05 06:00', '2026-10-05 18:00');
    turnoConComida($this->card, '2026-10-07 06:00', '2026-10-07 18:00', confirmar: false);

    $totales = app(MealAllowanceCalculator::class)->forEmployees([$this->employee->id], $this->tenant->id, CarbonImmutable::parse('2026-09-27'), CarbonImmutable::parse('2026-10-26'));

    expect($totales[$this->employee->id]['meals'])->toBe(3)
        // Desayuno y almuerzo a 10.000, merienda a 7.000.
        ->and($totales[$this->employee->id]['amount'])->toBe(27_000.0)
        ->and($totales[$this->employee->id]['counts']['almuerzo'])->toBe(1)
        ->and(array_keys($totales[$this->employee->id]['days']))->toBe(['2026-10-05']);
});

it('shows the meals of the period and downloads the payment sheet', function (): void {
    turnoConComida($this->card, '2026-10-05 06:00', '2026-10-05 18:00');
    turnoConComida($this->card, '2026-10-08 18:00', '2026-10-09 06:00');

    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
    $this->travelTo(Carbon::parse('2026-10-10 10:00', 'America/Bogota'));

    Livewire::test(Alimentacion::class)
        ->assertCanSeeTableRecords([$this->employee])
        ->assertTableColumnStateSet('total_comidas', 6, $this->employee)
        ->assertTableColumnStateSet('comida_desayuno', 2, $this->employee)
        ->assertTableColumnStateSet('valor', 54_000.0, $this->employee)
        ->assertSee('6 comidas por $ 54.000')
        ->callAction(TestAction::make('excelPago')->table())
        ->assertFileDownloaded('ALIMENTACION-20260927-20261026.xlsx');
});
