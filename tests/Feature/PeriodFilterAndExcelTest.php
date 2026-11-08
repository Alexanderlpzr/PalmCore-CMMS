<?php

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Domain\Reports\Excel\TableExcelExport;
use App\Filament\Filters\DateRangeFilter;
use App\Filament\Pages\FactorDeHoras;
use App\Filament\Pages\HorasExtras;
use App\Filament\Resources\AttendanceDays\Pages\ListAttendanceDays;
use App\Filament\Resources\AttendanceScans\Pages\ListAttendanceScans;
use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Infrastructure\Tenancy\CurrentTenant;
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
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\Permission\PermissionRegistrar;

/*
 * Pedido el 2026-11-08: en las tablas se elige cualquier mes, año, corte de nómina (del 27
 * al 26) o rango, y se descarga en Excel lo filtrado. Primera etapa: talento humano.
 *
 * Hoy es el 8 de noviembre de 2026; corte el 26: el corte en curso va del 27/10 al 26/11.
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

    $this->travelTo(Carbon::parse('2026-11-08 10:00', 'America/Bogota'));

    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Diego', 'last_name' => 'Medina', 'hire_date' => '2025-01-01']);
    $carne = EmployeeQrCode::factory()->forEmployee($this->employee)->create();

    // Un día en el corte de octubre (27/09–26/10) y otro en el de noviembre.
    foreach ([['2026-10-05 06:00', '2026-10-05 16:00'], ['2026-11-03 06:00', '2026-11-03 14:00']] as [$entrada, $salida]) {
        app(AttendanceService::class)->record($carne, at: Carbon::parse($entrada, 'America/Bogota')->utc());
        app(AttendanceService::class)->record($carne, at: Carbon::parse($salida, 'America/Bogota')->utc());
    }

    app(AttendanceDayBuilder::class)->buildForEmployee($this->employee, Carbon::parse('2026-10-01'), Carbon::parse('2026-11-07'));

    $rrhh = User::factory()->create(['is_active' => true]);
    $rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $rrhh->assignRole('talento-humano');
    $this->actingAs($rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('turns a month, a year or a payroll cutoff into dates', function (): void {
    expect(DateRangeFilter::periodWindow('corte:2026-10', 26))->sequence(
        fn ($desde) => $desde->toDateString()->toBe('2026-09-27'),
        fn ($hasta) => $hasta->toDateString()->toBe('2026-10-26'),
    )
        ->and(DateRangeFilter::periodWindow('mes:2026-02', null))->sequence(
            fn ($desde) => $desde->toDateString()->toBe('2026-02-01'),
            fn ($hasta) => $hasta->toDateString()->toBe('2026-02-28'),
        )
        ->and(DateRangeFilter::periodWindow('anio:2025', null))->sequence(
            fn ($desde) => $desde->toDateString()->toBe('2025-01-01'),
            fn ($hasta) => $hasta->toDateString()->toBe('2025-12-31'),
        )
        ->and(DateRangeFilter::periodWindow('corte:2026-10', null))->toBeNull();

    // Con día de corte se ofrecen los cortes, empezando por el que corre; sin él, no.
    $conCorte = DateRangeFilter::periodOptions(26);

    expect(array_key_first($conCorte['Cortes de nómina']))->toBe('corte:2026-11')
        ->and($conCorte['Cortes de nómina']['corte:2026-11'])->toBe('Corte de noviembre 2026 (27/10 al 26/11)')
        ->and($conCorte['Meses'])->toHaveKey('mes:2024-01')
        ->and($conCorte['Años'])->toHaveKeys(['anio:2026', 'anio:2024'])
        ->and(DateRangeFilter::periodOptions(null))->not->toHaveKey('Cortes de nómina');
});

it('opens the overtime on the running cutoff and lets choose any other', function (): void {
    $pantalla = Livewire::test(HorasExtras::class);

    expect(array_map(fn ($d) => $d->toDateString(), $pantalla->instance()->window()))->toBe(['2026-10-27', '2026-11-26']);

    $pantalla->filterTable('periodo', ['periodo' => 'corte:2026-10', 'desde' => '2026-09-27', 'hasta' => '2026-10-26']);

    expect(array_map(fn ($d) => $d->toDateString(), $pantalla->instance()->window()))->toBe(['2026-09-27', '2026-10-26']);

    $pantalla->callAction(TestAction::make('excelTodos')->table())
        ->assertFileDownloaded('formato-horas-extras-'.$this->employee->document_number.'-2026-10.xlsx');
});

it('filters the pending hours by period and downloads them in Excel', function (): void {
    $pantalla = Livewire::test(ListAttendanceDays::class)
        ->filterTable('periodo', ['periodo' => 'mes:2026-11'])
        ->assertCanSeeTableRecords($this->employee->attendanceDays()->whereDate('work_date', '2026-11-03')->get())
        ->assertCanNotSeeTableRecords($this->employee->attendanceDays()->whereDate('work_date', '2026-10-05')->get())
        ->callAction(TestAction::make('excelTabla')->table())
        ->assertFileDownloaded();

    // El libro dice el filtro y trae las columnas visibles con lo que se lee en pantalla.
    $ruta = (new TableExcelExport)->build($pantalla->instance(), 'Horas por confirmar')->close();
    $lector = new XlsxReader;
    $lector->open($ruta);
    $celdas = [];

    foreach ($lector->getSheetIterator() as $hoja) {
        foreach ($hoja->getRowIterator() as $fila) {
            $celdas = [...$celdas, ...$fila->toArray()];
        }
    }

    $lector->close();
    $texto = implode(' | ', array_map(fn ($c): string => $c instanceof DateTimeInterface ? $c->format('d/m/Y') : (string) $c, $celdas));

    expect($texto)->toContain('Horas por confirmar')
        ->toContain('Fecha: noviembre 2026')
        ->toContain('1 fila')
        ->toContain('Diego Medina')
        ->toContain('03/11/2026')
        ->not->toContain('05/10/2026');
});

it('picks a month on the gate marks and downloads them', function (): void {
    Livewire::test(ListAttendanceScans::class)
        ->filterTable('fecha', ['desde' => '2026-10-01', 'hasta' => '2026-10-31'])
        ->callAction(TestAction::make('excelTabla')->table())
        ->assertFileDownloaded();
});

it('filters the payrolls by month and downloads the list', function (): void {
    PayrollRun::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Nómina de octubre', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31']);
    $septiembre = PayrollRun::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Nómina de septiembre', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30']);

    Livewire::test(ListPayrollRuns::class)
        ->filterTable('periodo', ['periodo' => 'mes:2026-10'])
        ->assertCanNotSeeTableRecords([$septiembre])
        ->assertSee('Nómina de octubre')
        ->callAction(TestAction::make('excelTabla')->table())
        ->assertFileDownloaded();
});

it('downloads the hours factor indicator in Excel', function (): void {
    Livewire::test(FactorDeHoras::class)
        ->callAction('excel')
        ->assertFileDownloaded('FACTOR-DE-HORAS-20261027-20261108.xlsx');
});
