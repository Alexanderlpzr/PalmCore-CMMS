<?php

use App\Domain\HumanResources\Enums\CertificateType;
use App\Domain\HumanResources\Enums\EmployeeDocumentType;
use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Domain\HumanResources\Enums\PayrollRunStatus;
use App\Domain\HumanResources\Support\SpanishWords;
use App\Domain\Reports\Services\CertificateWordService;
use App\Exceptions\BusinessRuleException;
use App\Filament\Pages\Certificados;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Holiday;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * Los certificados de talento humano, pedidos el 2026-10-23 con sus formatos de Word: la
 * certificación de persona activa (TH-SOL-F-002) y los cuatro del egreso (TH-EGR-F-001 a
 * 004). Salen en esos mismos Word —el sistema solo cambia los datos de la persona y la
 * firma—, los expide un trabajador elegido por talento humano —hoy el director de planta—
 * y cada uno queda en la carpeta del trabajador.
 */

beforeEach(function (): void {
    Storage::fake(private_files_disk());
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $this->travelTo(Carbon::parse('2026-10-23 10:00', 'America/Bogota'));

    $this->director = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Franklin',
        'last_name' => 'Álvarez de los Reyes',
        'position' => 'Director de Planta',
        'document_number' => '74860123',
    ]);

    $this->danna = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Danna Fernanda',
        'last_name' => 'Torres Ortega',
        'sex' => 'F',
        'document_type' => 'CC',
        'document_number' => '1118533291',
        'document_issue_place' => 'Yopal (Casanare)',
        'position' => 'Calidad de Fruto',
        'contract_type' => 'indefinido',
        'base_salary' => 1_750_905,
        'hire_date' => '2026-03-02',
    ]);

    $this->carlos = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_code' => '068',
        'first_name' => 'Carlos Enrique',
        'last_name' => 'Arévalo Cano',
        'sex' => 'M',
        'document_number' => '1058324674',
        'document_issue_place' => 'Santa María (Boyacá)',
        'position' => 'Mecánico I',
        'contract_type' => 'indefinido',
        'severance_fund' => 'PORVENIR',
        'hire_date' => '2026-10-06',
        'termination_date' => '2026-10-30',
        'status' => EmploymentStatus::Retirado,
    ]);

    $this->service = app(CertificateWordService::class);
});

function ponerQuienExpide(Tenant $tenant, Employee $firmante): void
{
    Storage::disk(private_files_disk())->put('certificates/firma.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAYAAAD0In+KAAAAEUlEQVR42mNk+M/AwMDAwAAADgUBAa+XKrkAAAAASUVORK5CYII='));
    $tenant->forceFill(['settings' => array_merge($tenant->settings ?? [], ['human_resources' => ['certificates' => [
        'signer_employee_id' => $firmante->id,
        'signature_path' => 'certificates/firma.png',
        'exit_exam_provider' => 'Avancemos Salud IPS – Cra 7 #19-72 Maní, Casanare',
    ]]])])->save();
}

/** Las partes del Word que salió, por nombre. */
function wordDe(string $docx): array
{
    $ruta = tempnam(sys_get_temp_dir(), 'docx');
    file_put_contents($ruta, $docx);
    $zip = new ZipArchive;
    $zip->open($ruta);
    $partes = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $partes[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
    }

    $zip->close();
    unlink($ruta);

    return $partes;
}

/** Lo que dice el certificado: el texto de sus párrafos, uno tras otro. */
function textoDe(string $docx): string
{
    $xml = str_replace('</w:p>', ' ', wordDe($docx)['word/document.xml']);

    return preg_replace('/ {2,}/u', ' ', html_entity_decode(strip_tags($xml), ENT_XML1 | ENT_QUOTES, 'UTF-8'));
}

it('writes dates and money in words, as the formats do', function (): void {
    expect(SpanishWords::date(Carbon::parse('2026-10-06')))->toBe('seis (06) de octubre del año dos mil veintiséis (2026)')
        ->and(SpanishWords::date(Carbon::parse('2026-03-02'), padded: false))->toBe('dos (2) de marzo del año dos mil veintiséis (2026)')
        ->and(SpanishWords::date(Carbon::parse('2026-11-01')))->toBe('primero (01) de noviembre del año dos mil veintiséis (2026)')
        ->and(SpanishWords::daysOfMonth(Carbon::parse('2026-10-23')))->toBe('los veintitrés (23) días del mes de octubre de dos mil veintiséis (2026)')
        ->and(SpanishWords::amount(1_750_905))->toBe('un millón setecientos cincuenta mil novecientos cinco')
        ->and(SpanishWords::amount(2_000_000))->toBe('dos millones de')
        ->and(SpanishWords::amount(1_421_000))->toBe('un millón cuatrocientos veintiún mil')
        ->and(SpanishWords::figure(1_750_905))->toBe('1.750.905');
});

it('gives five business days for the exit exam, without holidays', function (): void {
    // Se retira el viernes 30/10; el lunes 2/11 es festivo: el plazo es el lunes 9.
    Holiday::factory()->create(['tenant_id' => $this->tenant->id, 'holiday_date' => '2026-11-02']);

    expect($this->service->exitExamDeadline($this->tenant, Carbon::parse('2026-10-30'))->toDateString())->toBe('2026-11-09');
});

it('fills the active employee certificate on the company Word, with the salary chosen', function (): void {
    ponerQuienExpide($this->tenant, $this->director);
    $base = $this->service->defaults(CertificateType::ActiveEmployment, $this->danna, $this->tenant, Carbon::today()->toImmutable());

    $word = $this->service->render(CertificateType::ActiveEmployment, $this->danna, $this->tenant, ['salary_note' => 'de tipo variable'] + $base);
    $texto = textoDe($word);

    expect($base['salary_mode'])->toBe('basic')
        ->and($texto)->toContain('Que la señora Danna Fernanda Torres Ortega, identificada con cédula de ciudadanía N° 1.118.533.291 expedida en Yopal (Casanare), labora en')
        // El resto es el formato de la empresa, tal cual.
        ->and($texto)->toContain('labora en Extractora El Pajuil Central del Casanare S.A.S. ZOMAC, NIT 901.271.583-2, desde el dos (2) de marzo del año dos mil veintiséis (2026)')
        ->and($texto)->toContain('desempeñando el cargo de Calidad de Fruto')
        ->and($texto)->toContain('☒ Indefinido')
        ->and($texto)->toContain('☐ Fijo')
        ->and($texto)->toContain('Un millón setecientos cincuenta mil novecientos cinco pesos m/cte. ($1.750.905) de tipo variable.')
        ->and($texto)->toContain('se expide en Maní – Casanare, a los veintitrés (23) días del mes de octubre de dos mil veintiséis (2026).')
        ->and($texto)->toContain('Franklin Álvarez de los Reyes')
        ->and($texto)->toContain('Director de Planta')
        ->and($texto)->not->toContain('{{')
        // La firma es la de quien expide, no la que traía el Word.
        ->and(wordDe($word)['word/media/image1.png'])->toBe(Storage::disk(private_files_disk())->get('certificates/firma.png'))
        // El encabezado y el pie son los del formato.
        ->and(strip_tags(wordDe($word)['word/header1.xml']))->toContain('TH-SOL-F-002')
        ->and(strip_tags(wordDe($word)['word/footer1.xml']))->toContain('Km 38 Vía a Santa Elena de Cusiva');

    // Sin salario la fila sale entera; o con el que escriba talento humano.
    expect(textoDe($this->service->render(CertificateType::ActiveEmployment, $this->danna, $this->tenant, ['salary_mode' => 'none'] + $base)))->not->toContain('Salario mensual')
        ->and(textoDe($this->service->render(CertificateType::ActiveEmployment, $this->danna, $this->tenant, ['salary_mode' => 'other', 'salary' => 2_000_000] + $base)))->toContain('Dos millones de pesos m/cte. ($2.000.000)');

    expect(fn () => $this->service->render(CertificateType::ActiveEmployment, $this->danna, $this->tenant, ['salary_mode' => 'other', 'salary' => null] + $base))
        ->toThrow(BusinessRuleException::class, 'Escriba el salario');
});

it('averages the salary of the last three closed payrolls', function (): void {
    ponerQuienExpide($this->tenant, $this->director);
    $nomina = function (string $mes, float $devengado, float $bono, PayrollRunStatus $estado = PayrollRunStatus::Cerrada): void {
        $corrida = PayrollRun::factory()->create(['tenant_id' => $this->tenant->id, 'name' => "Nómina {$mes}", 'period_start' => "{$mes}-01", 'period_end' => Carbon::parse("{$mes}-01")->endOfMonth()->toDateString(), 'status' => $estado]);
        PayrollEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_run_id' => $corrida->id,
            'employee_id' => $this->danna->id,
            'earned_with_surcharges' => $devengado,
            'bonus_constitutive' => $bono,
            'hours_bonus_total' => 0,
            'transport_allowance' => 249_095,
        ]);
    };

    expect($this->service->averageSalary($this->danna))->toBeNull();

    // La de junio queda por fuera: solo las tres últimas cerradas. El borrador no cuenta.
    $nomina('2026-06', 9_000_000, 0);
    $nomina('2026-07', 1_700_000, 0);
    $nomina('2026-08', 1_800_000, 50_000);
    $nomina('2026-09', 1_650_000, 0);
    $nomina('2026-10', 5_000_000, 0, PayrollRunStatus::Borrador);

    $promedio = $this->service->averageSalary($this->danna);

    expect($promedio['amount'])->toEqual(1_733_333.0)
        ->and($promedio['runs'])->toBe(['Nómina 2026-09', 'Nómina 2026-08', 'Nómina 2026-07'])
        ->and(textoDe($this->service->render(CertificateType::ActiveEmployment, $this->danna, $this->tenant, ['salary_mode' => 'average', 'issued_on' => '2026-10-23'])))
        ->toContain('($1.733.333)');
});

it('fills the four exit documents on their Word formats', function (): void {
    ponerQuienExpide($this->tenant, $this->director);
    Holiday::factory()->create(['tenant_id' => $this->tenant->id, 'holiday_date' => '2026-11-02']);
    $this->travelTo(Carbon::parse('2026-10-30 15:00', 'America/Bogota'));
    $hoy = Carbon::today()->toImmutable();
    $texto = fn (CertificateType $tipo, array $extra = []) => textoDe($this->service->render($tipo, $this->carlos, $this->tenant, $extra + $this->service->defaults($tipo, $this->carlos, $this->tenant, $hoy)));

    // El formato de persona inactiva dice «el (la) señor(a)» para todos: así viene.
    expect($texto(CertificateType::FormerEmployment))
        ->toContain('Que el (la) señor(a) Carlos Enrique Arévalo Cano, identificado(a) con cédula de ciudadanía N° 1.058.324.674 expedida en Santa María (Boyacá), laboró en')
        ->toContain('desde el seis (06) de octubre del año dos mil veintiséis (2026) hasta el treinta (30) de octubre del año dos mil veintiséis (2026)')
        ->toContain('Mecánico I')
        ->toContain('a los treinta (30) días del mes de octubre de dos mil veintiséis (2026).')
        ->not->toContain('{{');

    expect($texto(CertificateType::SeveranceWithdrawal))
        ->toContain('Sociedad Administradora de Fondos de Pensiones y Cesantías Porvenir S.A.')
        ->toContain('Autoriza el retiro total de las cesantías consignadas a nombre del señor Carlos Enrique Arévalo Cano, identificado con cédula de ciudadanía N° 1.058.324.674')
        ->toContain('desde el seis (06) de octubre del año dos mil veintiséis (2026) hasta el treinta (30) de octubre del año dos mil veintiséis (2026).')
        ->toContain('Nombre: Carlos Enrique Arévalo Cano')
        ->toContain('Fecha: 30/10/2026')
        ->not->toContain('{{');

    expect($texto(CertificateType::Clearance, ['delivered' => ['certificacion', 'liquidacion']]))
        ->toContain('Yo, Carlos Enrique Arévalo Cano, identificado(a) con cédula de ciudadanía N° 1.058.324.674 expedida en Santa María (Boyacá)')
        ->toContain('desde el seis (06) de octubre de dos mil veintiséis (2026) hasta el treinta (30) de octubre de dos mil veintiséis 2026, cumplió')
        ->toContain('☒ Certificación laboral')
        ->toContain('☐ Autorización para el examen médico de egreso')
        ->toContain('☒ Liquidación de prestaciones sociales')
        ->toContain('Nombre: Franklin Álvarez de los Reyes')
        ->toContain('C.C: 74.860.123')
        ->toContain('Cargo: Director de Planta')
        ->not->toContain('{{');

    expect($texto(CertificateType::ExitExam))
        ->toContain('Avancemos Salud IPS – Cra 7 #19-72 Maní, Casanare')
        ->toContain('Retiro: 30/10/2026')
        ->toContain('09/11/2026')
        ->not->toContain('{{');

    // Sin fecha de retiro no hay documento de egreso.
    expect(fn () => $this->service->render(CertificateType::FormerEmployment, $this->danna, $this->tenant, ['issued_on' => '2026-10-23']))
        ->toThrow(BusinessRuleException::class, 'Falta la fecha de retiro');
});

it('issues into the worker folder, and refuses without someone to sign', function (): void {
    $opciones = $this->service->defaults(CertificateType::FormerEmployment, $this->carlos, $this->tenant, Carbon::today()->toImmutable());

    expect(fn () => $this->service->issue(CertificateType::FormerEmployment, $this->carlos, $this->tenant, $opciones, User::factory()->create()))
        ->toThrow(BusinessRuleException::class, 'Quién expide');

    ponerQuienExpide($this->tenant, $this->director);
    $documento = $this->service->issue(CertificateType::FormerEmployment, $this->carlos, $this->tenant, $opciones, User::factory()->create());

    expect($documento->document_type)->toBe(EmployeeDocumentType::Certificado)
        ->and($documento->employee_id)->toBe($this->carlos->id)
        ->and($documento->file_name)->toBe('TH-EGR-F-001-068. Certificación laboral Carlos Enrique Arévalo Cano.docx')
        ->and($documento->mime_type)->toBe(CertificateWordService::MIME)
        ->and($documento->notes)->toBe('Expedido por Franklin Álvarez de los Reyes (Director de Planta).')
        ->and(Storage::disk(private_files_disk())->get($documento->file_path))->toStartWith('PK')
        // No es de la carpeta de ingreso: no cuenta como documento que falta.
        ->and(EmployeeDocumentType::Certificado->isRequired())->toBeFalse();
});

it('lets human resources choose who signs and issue from the page', function (): void {
    $rrhh = User::factory()->create(['is_active' => true]);
    $rrhh->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $rrhh->assignRole('talento-humano');
    $this->actingAs($rrhh);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(Certificados::class)
        ->assertOk()
        ->assertSee('Todavía nadie expide los certificados')
        ->callAction('quienExpide', data: [
            'signer_employee_id' => $this->director->id,
            'exit_exam_provider' => 'Avancemos Salud IPS',
        ])
        ->assertHasNoActionErrors()
        ->assertSee('Franklin Álvarez de los Reyes')
        ->assertSee('Sin firma escaneada')
        // Retirado: los cuatro del egreso, y no la certificación de persona activa.
        ->fillForm(['employee_id' => $this->carlos->id])
        ->assertFormFieldExists('type')
        ->fillForm(['type' => CertificateType::Clearance->value])
        ->assertFormSet(['start_date' => '2026-10-06', 'end_date' => '2026-10-30'])
        ->fillForm(['delivered' => ['certificacion']])
        ->call('expedir')
        ->assertFileDownloaded('TH-EGR-F-003-068. Paz y salvo Carlos Enrique Arévalo Cano.docx')
        ->assertSee('Últimos expedidos');

    expect($this->tenant->fresh()->certificateSettings()['signer_employee_id'])->toBe($this->director->id)
        ->and(EmployeeDocument::query()->where('employee_id', $this->carlos->id)->where('document_type', EmployeeDocumentType::Certificado)->count())->toBe(1)
        ->and(CertificateType::options(true))->toHaveCount(4)
        ->and(CertificateType::options(false))->toHaveKeys([CertificateType::ActiveEmployment->value]);
});

it('stays closed to whoever cannot see salaries', function (): void {
    $porteria = User::factory()->create(['is_active' => true]);
    $porteria->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $porteria->assignRole('porteria');
    $this->actingAs($porteria);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    expect(Certificados::canAccess())->toBeFalse();
});
