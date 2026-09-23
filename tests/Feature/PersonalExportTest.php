<?php

use App\Domain\Reports\Excel\PersonalExcelExport;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * El listado de Personal: orden de columnas, botón Volver y la exportación a Excel.
 *
 * De la exportación se prueba sobre todo lo que no debe hacer: mezclar horas firmadas con
 * horas propuestas, traer a quien el filtro dejó fuera, y entregar un salario a quien no
 * puede verlo en pantalla.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->operario = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Fermin',
        'last_name' => 'Beltran Vergara',
        'position' => 'Operario de Proceso I',
        'employee_code' => '001',
        'company_code' => 'O4092021',
        'base_salary' => 1_750_905,
        'blood_type' => 'O+',
    ]);

    $this->retirado = Employee::factory()->retired()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Ana Elsa',
        'last_name' => 'Gil Lopez',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function personalUser(string $role, Tenant $tenant): User
{
    $user = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);
    $user = $user->fresh();

    test()->actingAs($user);
    Filament::setTenant($tenant);

    return $user;
}

function diaDeTrabajo(Employee $employee, string $fecha, bool $confirmado, float $horas = 8, float $extras = 0): AttendanceDay
{
    $factory = AttendanceDay::factory()->forEmployee($employee);

    return ($confirmado ? $factory->confirmed() : $factory)->create([
        'work_date' => $fecha,
        'worked_hours' => $horas,
        'overtime_day_hours' => $extras,
    ]);
}

it('la tabla va en orden Cargo, Código, Nombre, Documento', function (): void {
    personalUser('talento-humano', $this->tenant);

    $columns = array_keys(Livewire::test(ListEmployees::class)->instance()->getTable()->getColumns());

    expect(array_slice($columns, 0, 4))->toBe(['position', 'employee_code', 'full_name', 'document_number']);
});

it('el listado tiene botón Volver y botón de Excel, y ya no el de PDF', function (): void {
    personalUser('talento-humano', $this->tenant);

    Livewire::test(ListEmployees::class)
        ->assertActionVisible('back')
        ->assertActionVisible('exportarExcel')
        ->assertActionDoesNotExist('descargarPersonal');
});

it('separa las horas confirmadas de las que faltan por firmar', function (): void {
    diaDeTrabajo($this->operario, '2026-08-03', confirmado: true, horas: 10, extras: 2);
    diaDeTrabajo($this->operario, '2026-08-04', confirmado: true, horas: 8);
    diaDeTrabajo($this->operario, '2026-08-05', confirmado: false, horas: 9);
    // De otro mes: no cuenta.
    diaDeTrabajo($this->operario, '2026-07-31', confirmado: true, horas: 8);

    $hours = app(PersonalExcelExport::class)->hoursByEmployee(
        [$this->operario->id],
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );

    expect($hours[$this->operario->id])->toBe([
        'days' => 2,
        'worked' => 18.0,
        'overtime' => 2.0,
        'surcharges' => 0.0,
        'pending' => 9.0,
    ]);
});

it('cada fila lleva la ficha, el salario como número y las horas del mes', function (): void {
    diaDeTrabajo($this->operario, '2026-08-03', confirmado: true, horas: 10, extras: 2);

    $row = app(PersonalExcelExport::class)
        ->rows(Employee::query()->whereKey($this->operario->id), Carbon::parse('2026-08-01'), includeSalary: true)
        ->sole();

    expect(array_slice(array_keys($row), 0, 5))->toBe(['Cargo', 'Código', 'Código empresarial', 'Nombres', 'Apellidos'])
        ->and($row['Código empresarial'])->toBe('O4092021')
        ->and($row['Salario básico'])->toBe(1_750_905.0)
        ->and($row['Horas trabajadas (confirmadas)'])->toBe(10.0)
        ->and($row['Horas extras'])->toBe(2.0)
        ->and($row['RH'])->toBe('O+')
        ->and($row['Carpeta'])->toBe('0/11');
});

it('trae solo a quien deja pasar el filtro', function (): void {
    $rows = app(PersonalExcelExport::class)
        ->rows(Employee::query()->where('status', 'activo'), Carbon::parse('2026-08-01'), includeSalary: true);

    expect($rows->pluck('Nombres')->all())->toBe(['Fermin']);
});

it('el salario no sale para quien no puede verlo', function (): void {
    $row = app(PersonalExcelExport::class)
        ->rows(Employee::query()->whereKey($this->operario->id), Carbon::parse('2026-08-01'), includeSalary: false)
        ->sole();

    expect($row)->not->toHaveKey('Salario básico')
        ->and($row)->not->toHaveKey('Tipo de salario');
});

it('el botón descarga el Excel con los filtros de la tabla', function (): void {
    personalUser('talento-humano', $this->tenant);

    Livewire::test(ListEmployees::class)
        ->callAction('exportarExcel', data: ['mes' => '2026-08'])
        ->assertHasNoActionErrors()
        ->assertFileDownloaded('PERSONAL-2026-08.xlsx');
});

// ── Los dos códigos ──────────────────────────────────────────────────────────

it('el código del trabajador se guarda a tres dígitos', function (): void {
    expect(Employee::formatCode('1'))->toBe('001')
        ->and(Employee::formatCode(17))->toBe('017')
        ->and(Employee::formatCode('004'))->toBe('004')
        ->and(Employee::formatCode('1234'))->toBe('1234')
        ->and(Employee::formatCode('A7'))->toBe('A7')
        ->and(Employee::formatCode(null))->toBeNull();
});

it('propone el siguiente consecutivo libre', function (): void {
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_code' => '004']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_code' => '017']);
    // Un código con letra no rompe la cuenta.
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'employee_code' => 'X1']);

    expect(Employee::nextCode($this->tenant->id))->toBe('018');
});

it('el consecutivo y el empresarial son campos distintos, y ambos salen en el Excel', function (): void {
    $this->operario->forceFill(['employee_code' => '001', 'company_code' => 'O4092021'])->save();

    $row = app(PersonalExcelExport::class)
        ->rows(Employee::query()->whereKey($this->operario->id), Carbon::parse('2026-08-01'), includeSalary: false)
        ->sole();

    expect($row['Código'])->toBe('001')
        ->and($row['Código empresarial'])->toBe('O4092021');
});

it('la ficha guarda el código a tres dígitos aunque se escriba suelto', function (): void {
    personalUser('talento-humano', $this->tenant);

    Livewire::test(EditEmployee::class, ['record' => $this->operario->getRouteKey()])
        ->fillForm(['employee_code' => '7', 'company_code' => '220820231'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->operario->refresh();

    expect($this->operario->employee_code)->toBe('007')
        ->and($this->operario->company_code)->toBe('220820231');
});

// ── Ordenar y agrupar ────────────────────────────────────────────────────────

it('ordena por nombre en los dos sentidos', function (): void {
    personalUser('talento-humano', $this->tenant);

    // Activos los dos: la tabla filtra por «Activo» de entrada.
    $zapata = Employee::factory()->create([
        'tenant_id' => $this->tenant->id, 'first_name' => 'Zoraida', 'last_name' => 'Zapata',
    ]);

    Livewire::test(ListEmployees::class)
        ->sortTable('full_name')
        ->assertCanSeeTableRecords([$this->operario, $zapata], inOrder: true)
        ->sortTable('full_name', 'desc')
        ->assertCanSeeTableRecords([$zapata, $this->operario], inOrder: true);
});

it('ordena por antigüedad, del más antiguo al más reciente', function (): void {
    personalUser('talento-humano', $this->tenant);

    $this->operario->forceFill(['hire_date' => '2021-09-04'])->save();
    $reciente = Employee::factory()->create([
        'tenant_id' => $this->tenant->id, 'hire_date' => '2026-01-08',
    ]);

    Livewire::test(ListEmployees::class)
        ->sortTable('hire_date')
        ->assertCanSeeTableRecords([$this->operario, $reciente], inOrder: true)
        ->sortTable('hire_date', 'desc')
        ->assertCanSeeTableRecords([$reciente, $this->operario], inOrder: true);
});

it('agrupa por área, cargo o estado sin agrupar por omisión', function (): void {
    personalUser('talento-humano', $this->tenant);

    $table = Livewire::test(ListEmployees::class)->instance()->getTable();

    expect(array_keys($table->getGroups()))->toBe(['area_specific', 'area', 'position', 'status'])
        ->and($table->getDefaultGroup())->toBeNull();
});

it('filtra por área general', function (): void {
    personalUser('talento-humano', $this->tenant);

    $this->operario->forceFill(['area' => 'operativo'])->save();
    $administrativo = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'area' => 'administrativo']);

    Livewire::test(ListEmployees::class)
        ->filterTable('area', 'administrativo')
        ->assertCanSeeTableRecords([$administrativo])
        ->assertCanNotSeeTableRecords([$this->operario]);
});
