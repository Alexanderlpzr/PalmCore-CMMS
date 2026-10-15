<?php

use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\OvertimeWorkbookImporter;
use App\Domain\HumanResources\Services\PayrollParameterService;
use App\Filament\Pages\HorasExtras;
use App\Infrastructure\Tenancy\CurrentTenant;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\PermissionRegistrar;

/*
 * El libro TH-NOM-P-001 llena los días que la portería no registró. La portería manda: un
 * día con marcas del carné no se toca; los turnos que no han terminado no entran; volver a
 * subir el libro no duplica nada.
 *
 * Hoy es el martes 13 de octubre de 2026 al mediodía.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);
    app(PayrollParameterService::class)->seedDefaults($this->tenant->id, Carbon::parse('2026-01-01'));
    $this->travelTo(Carbon::parse('2026-10-13 12:00', 'America/Bogota'));

    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'document_number' => '1.116.615.860', 'first_name' => 'Pedro', 'last_name' => 'Pérez']);

    // El 5 de octubre ya lo registró la portería con el carné.
    $carne = EmployeeQrCode::factory()->forEmployee($this->employee)->create();
    app(AttendanceService::class)->record($carne, at: Carbon::parse('2026-10-05 07:05', 'America/Bogota')->utc());
    app(AttendanceService::class)->record($carne, at: Carbon::parse('2026-10-05 17:50', 'America/Bogota')->utc());

    $this->rrhh = User::factory()->create(['is_active' => true]);
    $this->rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->rrhh->assignRole('talento-humano');

    $this->libro = libroParaImportar([
        'PEDRO' => [1116615860, [
            ['lunes, 28 de septiembre de 2026', '7:00am', '6:00pm'],
            // Turno de noche: «12:00pm» es la medianoche del día siguiente.
            ['jueves, 1 de octubre de 2026', '6:00pm', '12:00pm'],
            ['lunes, 5 de octubre de 2026', '7:00am', '6:00pm'],
            ['viernes, 16 de octubre de 2026', '7:00am', '3:00pm'],
        ]],
        'NADIE' => [999999, [['lunes, 28 de septiembre de 2026', '7:00am', '3:00pm']]],
    ]);
});

afterEach(function (): void {
    @unlink($this->libro);
});

/**
 * @param  array<string, array{0: int, 1: list<array{0: string, 1: string, 2: string}>}>  $hojas
 */
function libroParaImportar(array $hojas): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'libro').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($ruta);
    $primera = true;

    foreach ($hojas as $nombre => [$documento, $dias]) {
        $hoja = $primera ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
        $hoja->setName($nombre);
        $primera = false;

        $writer->addRow(Row::fromValues(['', '', '', 'FORMATO DE HORAS EXTRAS ']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['NOMBRE TRABAJADOR', str_replace(['JHONATAN C.', 'ADRIANA A.'], ['JHONATAN CONDIA', 'ADRIANA ARAGUA'], $nombre), '', '', '', '', '', '', '', '', '', '', 'IDENTIFICACIÓN', '', $documento]));
        $writer->addRow(Row::fromValues(['CARGO', 'OPERARIO']));
        $writer->addRow(Row::fromValues(['JEFE INMEDIATO', 'JEFE']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['FECHA', 'HORA INICIO', 'FIN HORARIO LABORAL', 'HORA      SALIDA', 'HORAS EXTRAS DIURNAS', 'HORAS EXTRAS NOCTURNAS', 'HORAS EXTRAS DOMINICAL DIURNA', 'HORAS EXTRAS DOMINICAL NOCTURNA', 'HORA DOMINICAL', 'RECARGO NOCTURNO', 'RECARGO NOCTURNO DOMINICAL', 'JUSTIFICACIÓN ']));

        foreach ($dias as [$fecha, $inicio, $salida]) {
            $writer->addRow(Row::fromValues([$fecha, $inicio, '', $salida, '', '', '', '', '', '', '', 'Turno']));
        }
    }

    $writer->close();

    return $ruta;
}

it('plans each day: what enters, what the gate already has, what has not ended and who is missing', function (): void {
    $plan = app(OvertimeWorkbookImporter::class)->plan($this->libro, $this->tenant);

    expect($plan['counts'])->toMatchArray(['import' => 2, 'has_marks' => 1, 'future' => 1, 'unknown_employee' => 1])
        ->and($plan['missingWorkers'])->toBe(['NADIE']);

    $noche = collect($plan['rows'])->first(fn (array $r): bool => $r['date']->toDateString() === '2026-10-01');
    expect($noche['exit']->format('Y-m-d H:i'))->toBe('2026-10-02 00:00');
});

it('loads the missing days as manual marks, builds them, and does not duplicate on a second upload', function (): void {
    $importador = app(OvertimeWorkbookImporter::class);
    $resultado = $importador->import($this->libro, $this->tenant, $this->rrhh);

    expect($resultado['imported'])->toBe(2)
        ->and(AttendanceScan::query()->where('notes', OvertimeWorkbookImporter::NOTE)->count())->toBe(4);

    $horas = AttendanceDay::query()->where('employee_id', $this->employee->id)->get()
        ->mapWithKeys(fn (AttendanceDay $d): array => [$d->work_date->toDateString() => (float) $d->worked_hours]);

    expect($horas['2026-09-28'])->toBe(11.0)
        ->and($horas['2026-10-01'])->toBe(6.0)
        // El de la portería sigue con sus horas reales.
        ->and(round($horas['2026-10-05'], 2))->toBe(10.75);

    expect($importador->import($this->libro, $this->tenant, $this->rrhh)['imported'])->toBe(0)
        ->and(AttendanceScan::query()->where('notes', OvertimeWorkbookImporter::NOTE)->count())->toBe(4);
});

it('imports from «Horas extras» with the uploaded workbook', function (): void {
    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(HorasExtras::class)
        ->callAction(TestAction::make('importarLibro')->table(), [
            'libro' => UploadedFile::fake()->createWithContent('TH-NOM-P-001.xlsx', file_get_contents($this->libro)),
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Se cargaron 2 días de 1 trabajador');

    expect(AttendanceScan::query()->where('notes', OvertimeWorkbookImporter::NOTE)->count())->toBe(4);
});

it('does not load one worker\'s hours onto another when a copied sheet kept someone else\'s ID', function (): void {
    $elias = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'document_number' => '1193126906', 'first_name' => 'Elías José', 'last_name' => 'García']);
    $jhonatan = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'document_number' => '1006000111', 'first_name' => 'Jhonatan', 'last_name' => 'Condia Ruiz']);

    // Las dos hojas copiadas conservan la cédula de Elías.
    $libro = libroParaImportar([
        'JHONATAN C.' => [1193126906, [['lunes, 28 de septiembre de 2026', '7:00am', '3:00pm']]],
        'ADRIANA A.' => [1193126906, [['martes, 29 de septiembre de 2026', '7:00am', '3:00pm']]],
    ]);
    // El reader toma el nombre de la fila «NOMBRE TRABAJADOR»: el de la hoja.
    $plan = app(OvertimeWorkbookImporter::class)->plan($libro, $this->tenant);
    @unlink($libro);

    $porHoja = collect($plan['rows'])->keyBy(fn (array $r): string => $r['date']->toDateString());

    expect($porHoja['2026-09-28']['employee']?->is($jhonatan))->toBeTrue()
        ->and($porHoja['2026-09-29']['employee'])->toBeNull()
        ->and($porHoja['2026-09-29']['status'])->toBe('unknown_employee')
        ->and(collect($plan['rows'])->contains(fn (array $r): bool => $r['employee']?->is($elias) ?? false))->toBeFalse();
});
