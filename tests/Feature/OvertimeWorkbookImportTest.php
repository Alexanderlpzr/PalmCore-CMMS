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
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\Notifications;
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

        // Fecha, inicio, salida y, si viene, el fin del horario laboral.
        foreach ($dias as $dia) {
            [$fecha, $inicio, $salida] = $dia;
            $writer->addRow(Row::fromValues([$fecha, $inicio, $dia[3] ?? '', $salida, '', '', '', '', '', '', '', 'Turno']));
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

it('recognizes a name written with one letter off, but not a sibling who shares the surname', function (): void {
    $geraldine = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'document_number' => '1118570286', 'first_name' => 'Geraldine Alejandra', 'last_name' => 'Escobar Martínez']);
    $jhonnatan = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'document_number' => '1116612077', 'first_name' => 'Jhonnatan David', 'last_name' => 'Condia Lara']);
    $ernesto = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'document_number' => '1116614560', 'first_name' => 'Ernesto', 'last_name' => 'Rincón Vergara']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'document_number' => '1116613220', 'first_name' => 'William Herney', 'last_name' => 'Rincon Vergara']);

    $empleados = Employee::query()->where('tenant_id', $this->tenant->id)->get();

    expect(OvertimeWorkbookImporter::match($empleados, '1118570286', 'GERALDIN ESCOBAR')?->is($geraldine))->toBeTrue()
        ->and(OvertimeWorkbookImporter::match($empleados, '1116612077', 'JHONATAN CONDIA')?->is($jhonnatan))->toBeTrue()
        // La hoja de William copiada con la cédula de Ernesto: el apellido no basta.
        ->and(OvertimeWorkbookImporter::match($empleados, '1116614560', 'WILLIAM RINCON')?->is($ernesto))->toBeFalse();
});

it('replaces with the workbook only the days it brings: voids what was there and rebuilds them', function (): void {
    // Una marca de la portería después de los días del libro: no se toca.
    $carne = $this->employee->qrCodes()->first();
    $despues = app(AttendanceService::class)->record($carne, at: Carbon::parse('2026-10-08 07:00', 'America/Bogota')->utc());

    $importador = app(OvertimeWorkbookImporter::class);
    $plan = $importador->plan($this->libro, $this->tenant, replace: true);

    expect($plan['window'][0]->toDateString())->toBe('2026-09-28')
        ->and($plan['window'][1]->toDateString())->toBe('2026-10-05')
        ->and($plan['toVoid'])->toBe(2)
        ->and($plan['counts']['import'])->toBe(3);

    $resultado = $importador->import($this->libro, $this->tenant, $this->rrhh, replace: true);

    expect($resultado['voided'])->toBe(2)
        ->and($resultado['imported'])->toBe(3)
        ->and(AttendanceScan::query()->withoutGlobalScopes()->where('void_reason', OvertimeWorkbookImporter::REPLACED_REASON)->count())->toBe(2)
        ->and($despues->refresh()->voided_at)->toBeNull();

    // El 5 de octubre ahora es el del libro: de 7 a 6, once horas.
    $dia = AttendanceDay::query()->where('employee_id', $this->employee->id)->whereDate('work_date', '2026-10-05')->sole();
    expect((float) $dia->worked_hours)->toBe(11.0);
});

it('counts as ordinary what the workbook schedules: no overtime on the night shift that ends on schedule', function (): void {
    // 6 p. m. a 6 a. m. con el fin del horario a las 6 a. m.: todo ordinario, con recargo nocturno.
    // 7 a. m. a 6 p. m. con «2:00am», que es 2:00 pm: siete ordinarias y cuatro extras.
    $libro = libroParaImportar([
        'PEDRO' => [1116615860, [
            ['martes, 29 de septiembre de 2026', '6:00pm', '6:00am', '6:00am'],
            ['sábado, 3 de octubre de 2026', '7:00am', '6:00pm', '2:00am'],
        ]],
    ]);

    app(OvertimeWorkbookImporter::class)->import($libro, $this->tenant, $this->rrhh);
    @unlink($libro);

    $dia = fn (string $fecha): AttendanceDay => AttendanceDay::query()->where('employee_id', $this->employee->id)->whereDate('work_date', $fecha)->sole();
    $noche = $dia('2026-09-29');
    $sabado = $dia('2026-10-03');

    expect((float) $noche->worked_hours)->toBe(12.0)
        ->and($noche->hours()->overtimeHours())->toBe(0.0)
        ->and((float) $noche->night_surcharge_hours)->toBeGreaterThan(0.0)
        ->and((float) $sabado->worked_hours)->toBe(11.0)
        ->and(round($sabado->hours()->overtimeHours(), 2))->toBe(4.0);
});

it('reads the scheduled end of the workbook, fixing a. m. and p. m. mixed up', function (string $fin, string $entrada, string $salida, ?string $esperado): void {
    $tz = 'America/Bogota';
    $e = CarbonImmutable::parse("2026-10-03 {$entrada}", $tz);
    $x = CarbonImmutable::parse("2026-10-03 {$salida}", $tz);
    $x = $x->lte($e) ? $x->addDay() : $x;

    expect(OvertimeWorkbookImporter::scheduledEnd($fin, $e, $x, $tz)?->format('Y-m-d H:i'))->toBe($esperado);
})->with([
    'noche, fin igual a la salida' => ['06:00', '18:00', '06:00', '2026-10-04 06:00'],
    'día normal' => ['14:00', '07:00', '18:00', '2026-10-03 14:00'],
    '«2:00am» que es 2:00 pm' => ['02:00', '07:00', '18:00', '2026-10-03 14:00'],
    'fin después de la salida' => ['20:00', '07:00', '15:00', null],
    'sin fin' => ['', '07:00', '15:00', null],
]);

it('warns about every error of the workbook before importing', function (): void {
    $libro = libroParaImportar([
        'PEDRO' => [123, [
            ['lunes, 28 de septiembre de 2026', '7:00am', '6:00pm', '2:00am'],
            ['martes, 29 de septiembre de 2026', '7:00am', '6:00pm', '6:30am'],
            ['miércoles, 30 de septiembre de 2026', '6:00am', '11:00pm'],
            ['jueves, 1 de octubre de 2026', '6:00', '2:00pm'],
            ['viernes, 2 de octubre de 2026', '7:00am', ''],
            ['sábado, 3 de octubre de 2026', 'siete', '3:00pm'],
            ['3/10/2026', '7:00am', '3:00pm'],
            ['lunes, 28 de septiembre de 2026', '7:00am', '6:00pm'],
        ]],
        // Otra hoja del mismo trabajador.
        'PEDRO P' => [1116615860, [['martes, 6 de octubre de 2026', '7:00am', '3:00pm']]],
    ]);

    $avisos = app(OvertimeWorkbookImporter::class)->plan($libro, $this->tenant)['issues'];
    @unlink($libro);

    $tiene = fn (string $texto): bool => collect($avisos)->contains(fn (string $aviso): bool => str_contains($aviso, $texto));

    expect($tiene('Hoja PEDRO: la cédula 123 no es la de la ficha de Pedro Pérez'))->toBeTrue()
        ->and($tiene('Hoja PEDRO P: es del mismo trabajador que la hoja PEDRO'))->toBeTrue()
        ->and($tiene('28/09/2026: el fin horario 2:00 am no cuadraba con el turno; se tomó como 2:00 pm'))->toBeTrue()
        ->and($tiene('29/09/2026: el fin horario 6:30 am no cae dentro del turno'))->toBeTrue()
        ->and($tiene('30/09/2026: turno de 17 horas'))->toBeTrue()
        ->and($tiene('la hora «6:00» de «HORA INICIO» no dice a. m. ni p. m.; se tomó como 06:00'))->toBeTrue()
        ->and($tiene('el 02/10/2026 tiene hora de inicio pero no de salida'))->toBeTrue()
        ->and($tiene('no se entiende la hora «siete» de «HORA INICIO»'))->toBeTrue()
        ->and($tiene('el 03/10/2026 tiene hora de salida pero no de inicio'))->toBeTrue()
        ->and($tiene('no se entiende la fecha «3/10/2026»'))->toBeTrue()
        ->and($tiene('el 28/09/2026 ya está en la fila 10'))->toBeTrue();
});

it('warns about the sheets it cannot read and the columns they lack', function (): void {
    $ruta = tempnam(sys_get_temp_dir(), 'libro').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($ruta);
    $writer->getCurrentSheet()->setName('ROTA');
    $writer->addRow(Row::fromValues(['NOMBRE TRABAJADOR', 'PEDRO PÉREZ']));
    $writer->addRow(Row::fromValues(['DÍA', 'HORA INICIO', 'HORA SALIDA']));
    $writer->addRow(Row::fromValues(['lunes, 28 de septiembre de 2026', '7:00am', '3:00pm']));
    $writer->addNewSheetAndMakeItCurrent()->setName('SIN FIN');
    $writer->addRow(Row::fromValues(['NOMBRE TRABAJADOR', 'PEDRO PÉREZ', 'IDENTIFICACIÓN', '1116615860']));
    $writer->addRow(Row::fromValues(['FECHA', 'HORA INICIO', 'HORA      SALIDA']));
    $writer->addRow(Row::fromValues(['martes, 29 de septiembre de 2026', '7:00am', '3:00pm']));
    $writer->close();

    $avisos = app(OvertimeWorkbookImporter::class)->plan($ruta, $this->tenant)['issues'];
    @unlink($ruta);

    expect($avisos)->toContain('Hoja ROTA: no se leyó, le falta la fila de títulos con «FECHA» en la columna A.')
        ->toContain('Hoja SIN FIN: no tiene la columna «FIN HORARIO LABORAL»; sus días se cuentan con la jornada de cada día.');
});

it('has nothing to warn about a clean workbook, midnight written as «12:00pm» included', function (): void {
    $libro = libroParaImportar([
        'PEDRO' => [1116615860, [
            ['lunes, 28 de septiembre de 2026', '7:00am', '6:00pm', '2:00pm'],
            ['martes, 29 de septiembre de 2026', '5:00pm', '12:00pm', '12:00pm'],
        ]],
    ]);

    expect(app(OvertimeWorkbookImporter::class)->plan($libro, $this->tenant)['issues'])->toBe([]);
    @unlink($libro);
});

it('shows the warnings of the workbook when importing from «Horas extras»', function (): void {
    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $libro = libroParaImportar(['PEDRO' => [123, [['lunes, 28 de septiembre de 2026', '7:00am', '6:00pm']]]]);

    Livewire::test(HorasExtras::class)
        ->callAction(TestAction::make('importarLibro')->table(), [
            'libro' => UploadedFile::fake()->createWithContent('TH-NOM-P-001.xlsx', file_get_contents($libro)),
        ]);
    @unlink($libro);

    $avisos = new Notifications;
    $avisos->mount();
    $notificacion = $avisos->notifications->last();

    expect($notificacion->getTitle())->toBe('Se cargó 1 día de 1 trabajador')
        ->and((string) $notificacion->getBody())->toContain('Revise en el libro 1 aviso')
        ->toContain('la cédula 123 no es la de la ficha de Pedro Pérez');
});

it('lists the warnings of the workbook as a list in the summary before importing, not as code', function (): void {
    $this->actingAs($this->rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $libro = libroParaImportar(['PEDRO' => [123, [['lunes, 28 de septiembre de 2026', '7:00am', '6:00pm']]]]);

    Livewire::test(HorasExtras::class)
        ->mountAction(TestAction::make('importarLibro')->table())
        ->fillForm(['libro' => UploadedFile::fake()->createWithContent('TH-NOM-P-001.xlsx', file_get_contents($libro))])
        ->assertMountedActionModalSeeHtml('<li>Hoja PEDRO: la cédula 123 no es la de la ficha de Pedro Pérez');
    @unlink($libro);
});
