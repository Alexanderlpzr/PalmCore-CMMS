<?php

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Domain\Reports\Excel\FormatoHorasExtrasExcel;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\RelationManagers\OvertimeRelationManager;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
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
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\PermissionRegistrar;

/*
 * El formato de horas extras (TH-FOR-002) para todos los que marcan: en Excel, con una hoja
 * por trabajador como el libro de talento humano; en la ficha, en la pestaña «Horas
 * extras»; y con el jefe inmediato que firma el «Vo. Bo.».
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

    foreach ([[PayrollParameter::MonthlyHoursDivisor, 210], [PayrollParameter::OrdinaryHoursPerDay, 7], [PayrollParameter::HoursCutoffDay, 26], [PayrollParameter::OvertimeExcessAsBonus, 1]] as [$parametro, $valor]) {
        $parametros->setValue($parametro, $valor, Carbon::parse('2026-01-01'), $this->tenant->id);
    }

    $this->diego = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Diego', 'last_name' => 'Medina Rojas', 'document_number' => '1116616864', 'position' => 'Operario de Proceso I', 'immediate_supervisor' => 'Edwin Marsiglia']);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Ana', 'last_name' => 'Pérez']);
    $this->sinMarcas = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Zoila', 'last_name' => 'Sin Marcas']);

    // Diego: el 28 de septiembre (mes anterior) y el 5 de octubre, de 7 a 6; Ana, el 5.
    foreach ([[$this->diego, '2026-09-28'], [$this->diego, '2026-10-05'], [$this->ana, '2026-10-05']] as [$trabajador, $fecha]) {
        $carne = $trabajador->qrCode ?? EmployeeQrCode::factory()->forEmployee($trabajador)->create();
        app(AttendanceService::class)->record($carne, at: Carbon::parse("{$fecha} 07:00", 'America/Bogota')->utc());
        app(AttendanceService::class)->record($carne, at: Carbon::parse("{$fecha} 18:00", 'America/Bogota')->utc());
        app(AttendanceDayBuilder::class)->buildForEmployee($trabajador, Carbon::parse($fecha), Carbon::parse($fecha));
    }

    $this->rrhh = User::factory()->create(['is_active' => true]);
    $this->rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->rrhh->assignRole('talento-humano');
    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $this->travelTo(Carbon::parse('2026-10-07 10:00', 'America/Bogota'));
});

/** @return array<string, array<int, array<int, mixed>>> hoja => filas, con índices base 1 */
function leerLibroDelFormato(string $ruta): array
{
    // Con las filas vacías: así los números de fila son los del libro.
    $opciones = new ReaderOptions;
    $opciones->SHOULD_PRESERVE_EMPTY_ROWS = true;
    $reader = new Reader($opciones);
    $reader->open($ruta);
    $hojas = [];

    foreach ($reader->getSheetIterator() as $hoja) {
        foreach ($hoja->getRowIterator() as $n => $fila) {
            $hojas[$hoja->getName()][$n] = array_combine(range(1, count($fila->getCells()) ?: 1), array_map(fn ($c) => $c->getValue(), $fila->getCells()) ?: ['']);
        }
    }

    $reader->close();

    return $hojas;
}

it('writes one sheet per worker with the heading, the worker and the days of the format', function (): void {
    $ruta = app(FormatoHorasExtrasExcel::class)->write(collect([$this->diego, $this->ana]), CarbonImmutable::parse('2026-09-27'), CarbonImmutable::parse('2026-10-26'), showValues: true);
    $libro = leerLibroDelFormato($ruta);
    @unlink($ruta);

    expect(array_keys($libro))->toBe(['DIEGO MEDINA', 'ANA PÉREZ']);

    $hoja = $libro['DIEGO MEDINA'];
    $texto = collect($hoja)->flatten()->filter()->map(fn ($v) => (string) $v);

    expect($hoja[1][2])->toBe('FORMATO DE HORAS EXTRAS')
        ->and($texto)->toContain('CÓDIGO: TH-FOR-002', 'VERSIÓN: 02', 'FECHA: 27/11/2020')
        ->and($hoja[5][2])->toBe('DIEGO MEDINA ROJAS')
        ->and($texto)->toContain('1116616864', 'OPERARIO DE PROCESO I', 'EDWIN MARSIGLIA')
        ->and($hoja[10])->toContain('HORAS EXTRAS DIURNAS', 'RECARGO NOCTURNO', 'JUSTIFICACIÓN', 'BONO HED')
        // Treinta días del corte, una fila cada uno.
        ->and($texto)->toContain('domingo, 27 de septiembre de 2026', 'lunes, 26 de octubre de 2026')
        ->and($texto)->toContain('7:00am', '2:00pm', '6:00pm')
        ->and($texto->filter(fn (string $v): bool => str_starts_with($v, 'Valor hora:')))->toHaveCount(1)
        ->and($texto->filter(fn (string $v): bool => str_starts_with($v, 'Vo. Bo. Jefe inmediato — Edwin Marsiglia')))->toHaveCount(1);
});

it('leaves the values out for whoever does not see salaries', function (): void {
    $ruta = app(FormatoHorasExtrasExcel::class)->write(collect([$this->diego]), CarbonImmutable::parse('2026-09-27'), CarbonImmutable::parse('2026-10-26'), showValues: false);
    $texto = collect(leerLibroDelFormato($ruta))->flatten()->filter()->map(fn ($v) => (string) $v);
    @unlink($ruta);

    expect($texto->filter(fn (string $v): bool => str_starts_with($v, 'Valor hora:')))->toBeEmpty();
});

it('downloads from Personal the sheets of everyone who clocked in during the period', function (): void {
    Livewire::test(ListEmployees::class)
        ->mountAction('formatoHoras')
        ->assertSchemaStateSet(['periodo' => '2026-09-27|2026-10-26'], 'mountedActionSchema0')
        ->callMountedAction()
        ->assertFileDownloaded('formato-horas-extras-personal-2026-10.xlsx');
});

it('shows the format in the «Horas extras» tab of the record', function (): void {
    $dias = AttendanceDay::query()->where('employee_id', $this->diego->id)->get();

    Livewire::test(OvertimeRelationManager::class, ['ownerRecord' => $this->diego, 'pageClass' => EditEmployee::class])
        ->assertCanSeeTableRecords($dias)
        ->assertSee('Horas del 27/09/2026 al 26/10/2026')
        ->assertSee('7:00 am')
        ->assertSee('Bonificación')
        ->callAction(TestAction::make('formatoExcel')->table())
        ->assertFileDownloaded('formato-horas-extras-1116616864-2026-10.xlsx');

    Livewire::test(OvertimeRelationManager::class, ['ownerRecord' => $this->diego, 'pageClass' => EditEmployee::class])
        ->callAction(TestAction::make('formatoPdf')->table())
        ->assertFileDownloaded('formato-horas-1116616864-2026-10.pdf');
});

it('loads the immediate supervisor from the overtime workbook', function (): void {
    $ruta = tempnam(sys_get_temp_dir(), 'jefes').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($ruta);
    $writer->getCurrentSheet()->setName('ANA');

    foreach ([['', '', '', 'FORMATO DE HORAS EXTRAS'], [], [], [], ['NOMBRE TRABAJADOR', 'ANA PÉREZ', '', 'IDENTIFICACIÓN', $this->ana->document_number], ['CARGO', 'ANALISTA'], ['JEFE INMEDIATO', 'DANIEL BARRIOS'], [], ['FECHA', 'HORA INICIO']] as $valores) {
        $writer->addRow(Row::fromValues($valores));
    }

    $writer->close();

    $this->artisan('hr:import-supervisors', ['file' => $ruta, '--tenant' => $this->tenant->slug])->assertSuccessful();
    expect($this->ana->fresh()->immediate_supervisor)->toBeNull();

    $this->artisan('hr:import-supervisors', ['file' => $ruta, '--tenant' => $this->tenant->slug, '--apply' => true])->assertSuccessful();
    expect($this->ana->fresh()->immediate_supervisor)->toBe('Daniel Barrios');

    @unlink($ruta);
});

it('lists the last cutoff windows, the current one first', function (): void {
    expect(array_keys(PayrollRun::recentHoursWindows(26, 3, CarbonImmutable::parse('2026-10-07'))))
        ->toBe(['2026-09-27|2026-10-26', '2026-08-27|2026-09-26', '2026-07-27|2026-08-26'])
        ->and(array_keys(PayrollRun::recentHoursWindows(0, 2, CarbonImmutable::parse('2026-10-07'))))
        ->toBe(['2026-10-01|2026-10-31', '2026-09-01|2026-09-30']);
});
