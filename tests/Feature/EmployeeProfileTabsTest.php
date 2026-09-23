<?php

use App\Domain\HumanResources\Enums\EmployeeDocumentType;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\RelationManagers\DocumentsRelationManager;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * La ficha del trabajador en pestañas: «Información» y «Documentos».
 *
 * Lo que más importa aquí no es la pestaña sino quién abre la carpeta. Portería ve al
 * trabajador para saber a quién escaneó; su examen médico no es asunto suyo.
 */

beforeEach(function (): void {
    Storage::fake(private_files_disk());

    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->employee = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Laura Fernanda',
        'last_name' => 'Pérez González',
        'position' => 'Ingeniera Residente',
        'document_number' => '11223718812',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function fichaUserWithRole(string $role, Tenant $tenant): User
{
    $user = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);
    $user = $user->fresh();

    // Filament exige usuario autenticado para fijar el tenant.
    test()->actingAs($user);
    Filament::setTenant($tenant);

    return $user;
}

it('la ficha se titula con el nombre y combina la información con las pestañas', function (): void {
    $this->actingAs(fichaUserWithRole('talento-humano', $this->tenant));

    $page = Livewire::test(EditEmployee::class, ['record' => $this->employee->getRouteKey()]);

    $page->assertOk()
        ->assertSee('Laura Fernanda Pérez González')
        ->assertSee('Ingeniera Residente · CC 11223718812 · Activo');

    expect($page->instance()->hasCombinedRelationManagerTabsWithContent())->toBeTrue()
        ->and($page->instance()->getContentTabLabel())->toBe('Información');
});

it('guarda los datos personales y el contacto de emergencia', function (): void {
    $this->actingAs(fichaUserWithRole('talento-humano', $this->tenant));

    Livewire::test(EditEmployee::class, ['record' => $this->employee->getRouteKey()])
        ->fillForm([
            'birth_date' => '1992-04-12',
            'blood_type' => 'O+',
            'allergies' => 'Ninguna',
            'city' => 'Milán',
            'phone' => '3214624422',
            'email' => 'laura@example.com',
            'emergency_contact_name' => 'Yasmin Rojas',
            'emergency_contact_relationship' => 'Cónyuge',
            'emergency_contact_phone' => '3214624422',
            'contract_type' => 'fijo',
            'compensation_fund' => 'Comfacaquetá',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->employee->refresh();

    expect($this->employee->birth_date->toDateString())->toBe('1992-04-12')
        ->and($this->employee->blood_type)->toBe('O+')
        ->and($this->employee->emergency_contact_name)->toBe('Yasmin Rojas')
        ->and($this->employee->contract_type)->toBe('fijo')
        ->and($this->employee->compensation_fund)->toBe('Comfacaquetá');
});

it('sube un documento a la carpeta, al disco privado', function (): void {
    $this->actingAs(fichaUserWithRole('talento-humano', $this->tenant));

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $this->employee,
        'pageClass' => EditEmployee::class,
    ])
        ->callAction(TestAction::make('subir')->table(), data: [
            'document_type' => EmployeeDocumentType::Examenes->value,
            'title' => 'Examen médico de ingreso',
            'files' => [UploadedFile::fake()->create('examen.pdf', 100, 'application/pdf')],
            'expires_at' => now()->addYear()->toDateString(),
        ])
        ->assertHasNoActionErrors();

    $document = $this->employee->documents()->sole();

    expect($document->title)->toBe('Examen médico de ingreso')
        ->and($document->tenant_id)->toBe($this->tenant->id)
        ->and($document->file_name)->toBe('examen.pdf')
        ->and($document->file_path)->toStartWith("employee-documents/{$this->tenant->id}/{$this->employee->id}/");

    Storage::disk(private_files_disk())->assertExists($document->file_path);
});

it('sube varios archivos de una vez, uno por fila', function (): void {
    // La cédula tiene dos caras y los exámenes son varios informes: se escriben el tipo y
    // la descripción una sola vez, y cada archivo queda como documento aparte.
    $this->actingAs(fichaUserWithRole('talento-humano', $this->tenant));

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $this->employee,
        'pageClass' => EditEmployee::class,
    ])
        ->callAction(TestAction::make('subir')->table(), data: [
            'document_type' => EmployeeDocumentType::Cedula->value,
            'files' => [
                UploadedFile::fake()->create('cedula-frente.pdf', 50, 'application/pdf'),
                UploadedFile::fake()->create('cedula-reverso.pdf', 50, 'application/pdf'),
            ],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('2 documentos subidos');

    $documents = $this->employee->documents()->get();

    expect($documents)->toHaveCount(2)
        ->and($documents->pluck('file_name')->sort()->values()->all())
        ->toBe(['cedula-frente.pdf', 'cedula-reverso.pdf'])
        // Sin descripción, el documento se llama como su tipo, y los dos cuentan como
        // una sola cédula en el checklist.
        ->and($documents->pluck('title')->unique()->all())->toBe(['Cédula de ciudadanía'])
        ->and($this->employee->fresh()->load('documents')->requiredDocumentsProgress())->toBe('1/11');

    foreach ($documents as $document) {
        Storage::disk(private_files_disk())->assertExists($document->file_path);
    }
});

it('el botón de subir está en la pestaña y también en la carpeta vacía', function (): void {
    $this->actingAs(fichaUserWithRole('talento-humano', $this->tenant));

    // Se fue una vez: el checklist ocupaba la cabecera de la tabla, que es donde Filament
    // pinta este botón, y la carpeta no se podía llenar.
    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $this->employee,
        'pageClass' => EditEmployee::class,
    ])
        ->assertActionVisible(TestAction::make('subir')->table())
        ->assertSee('0 de 11 documentos obligatorios');
});

it('portería no ve la pestaña de documentos', function (): void {
    $porteria = fichaUserWithRole('porteria', $this->tenant);
    $this->actingAs($porteria);

    expect(DocumentsRelationManager::canViewForRecord($this->employee, EditEmployee::class))->toBeFalse();

    $this->actingAs(fichaUserWithRole('talento-humano', $this->tenant));

    expect(DocumentsRelationManager::canViewForRecord($this->employee, EditEmployee::class))->toBeTrue();
});

it('descarga el documento a quien lleva la carpeta y se lo niega a portería', function (): void {
    $document = EmployeeDocument::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'file_path' => 'employee-documents/cedula.pdf',
        'file_name' => 'cedula.pdf',
    ]);
    Storage::disk(private_files_disk())->put($document->file_path, 'pdf');

    $this->actingAs(fichaUserWithRole('talento-humano', $this->tenant))
        ->get(route('employee-documents.download', $document))
        ->assertOk()
        ->assertDownload('cedula.pdf');

    $this->actingAs(fichaUserWithRole('porteria', $this->tenant))
        ->get(route('employee-documents.download', $document))
        ->assertForbidden();
});

it('no descarga documentos de otro tenant', function (): void {
    $otroTenant = Tenant::factory()->create();
    $ajeno = Employee::factory()->create(['tenant_id' => $otroTenant->id]);
    $document = EmployeeDocument::factory()->create([
        'tenant_id' => $otroTenant->id,
        'employee_id' => $ajeno->id,
    ]);

    $this->actingAs(fichaUserWithRole('talento-humano', $this->tenant))
        ->get(route('employee-documents.download', $document))
        ->assertForbidden();
});

it('el checklist dice qué documentos obligatorios faltan', function (): void {
    fichaUserWithRole('talento-humano', $this->tenant);

    foreach ([EmployeeDocumentType::HojaDeVida, EmployeeDocumentType::Cedula, EmployeeDocumentType::Otro] as $type) {
        EmployeeDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee->id,
            'document_type' => $type,
        ]);
    }

    // Dos del mismo tipo no cuentan doble, y «Otro» no completa nada.
    EmployeeDocument::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'document_type' => EmployeeDocumentType::Cedula,
    ]);

    $employee = $this->employee->fresh()->load('documents');

    expect($employee->requiredDocumentsProgress())->toBe('2/11')
        ->and($employee->missingRequiredDocuments())->not->toContain(EmployeeDocumentType::Cedula)
        ->and($employee->missingRequiredDocuments()[0])->toBe(EmployeeDocumentType::CertificadoBancario);

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $this->employee,
        'pageClass' => EditEmployee::class,
    ])
        ->assertSee('2 de 11 documentos obligatorios')
        ->assertSee('faltan 9')
        // «Subir documento» propone el primero que falta.
        ->mountAction(TestAction::make('subir')->table())
        ->assertSchemaStateSet(['document_type' => EmployeeDocumentType::CertificadoBancario->value]);
});

it('sin descripción, el documento se llama como su tipo', function (): void {
    $document = EmployeeDocument::factory()->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'document_type' => EmployeeDocumentType::AfiliacionArl,
        'title' => null,
    ]);

    expect($document->title)->toBe('Certificado afiliación ARL');
});

it('el filtro «Carpeta incompleta» cuenta tipos distintos, no filas', function (): void {
    fichaUserWithRole('talento-humano', $this->tenant);

    $completo = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

    foreach (EmployeeDocumentType::required() as $type) {
        EmployeeDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $completo->id,
            'document_type' => $type,
        ]);
    }

    // Once cédulas no son una carpeta completa.
    EmployeeDocument::factory()->count(11)->create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'document_type' => EmployeeDocumentType::Cedula,
    ]);

    Livewire::test(ListEmployees::class)
        ->filterTable('carpeta_incompleta', true)
        ->assertCanSeeTableRecords([$this->employee])
        ->assertCanNotSeeTableRecords([$completo]);
});
