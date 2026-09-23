<?php

use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Tenant;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/*
 * La carga de la hoja «Base datos» del libro de requerimientos de El Pajuil.
 *
 * Se escribe un libro con la misma forma que el real —título arriba, encabezados en la
 * fila 4, columnas con tilde y espacios de sobra— porque justo esas rarezas son las que
 * rompen un importador que lee por posición.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
});

/**
 * @param  list<list<mixed>>  $workers
 */
function rosterWorkbook(array $workers): string
{
    $path = tempnam(sys_get_temp_dir(), 'roster').'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('Base datos');

    $writer->addRow(Row::fromValues(['', '', 'REGISTRO DE TRABAJADOR']));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues([
        'CODIGO', 'CÓDIGO EMPRESARIAL', 'ESTADO', 'NOMBRE', 'CÉDULA', 'CARGO', 'AREA GENERAL',
        'AREA ESPECIFICA', 'FECHA DE INGRESO', 'TIPO DE CONTRATO', 'SEXO', 'HIJOS', 'BONO RODAMIENTO',
        'CORREO', 'CELULAR', 'FECHA DE NACIMIENTO', 'DIA ', 'MES', 'EDAD', 'FECHA DE EXPEDICIÓN',
        'LUGAR DE EXPEDICION', 'CONTACTO EMERGENCIA', 'RH', 'DIRECCION', 'MUNICIPIO/VEREDA', 'EPS',
        'FONDO CESANTIAS', 'FONDO PENSIONES', 'ARL', 'CAJA COMPENSACIÓN', 'T.CAMISA', 'T.PANTALON',
        'T.BOTAS', 'ROMPE VIENTOS',
    ]));

    foreach ($workers as $worker) {
        $writer->addRow(Row::fromValues($worker));
    }

    $writer->close();

    return $path;
}

it('carga la ficha completa y normaliza lo que el libro escribe de varias formas', function (): void {
    $file = rosterWorkbook([[
        4, 'O4082022', 'ACTIVO', 'Deysi Jazmin Lancheros Villamil', 1116612028, 'Aux. SST', 'ADMINISTRATIVO',
        'OFICIOS VARIOS', new DateTimeImmutable('2022-08-04'), 'INDEFINIDO', ' M', 'SI', 367850,
        'Dejala.Vi@gmail.com', '321 491 6527', new DateTimeImmutable('1984-02-08'), 8, 'FEBRERO', 42.6,
        new DateTimeImmutable('2002-03-20'), 'MANI-CASANARE', 3214774592, 'A+', 'SANTA ELENA', 'MANI-CASANARE',
        'NUEVA EPS', 'PORVENIR', 'PORVENIR', 'BOLIVAR', 'COMFACASANARE', 'L', 14, 36, 'XL',
    ]]);

    $this->artisan('hr:import-roster', ['file' => $file, '--tenant' => $this->tenant->id])->assertSuccessful();

    $employee = Employee::withoutGlobalScopes()->where('document_number', '1116612028')->sole();

    expect($employee->first_name)->toBe('Deysi Jazmin')
        ->and($employee->last_name)->toBe('Lancheros Villamil')
        // Los dos códigos, cada uno de su columna: el consecutivo a tres dígitos y el
        // empresarial tal como viene en el libro.
        ->and($employee->employee_code)->toBe('004')
        ->and($employee->company_code)->toBe('O4082022')
        ->and($employee->status)->toBe(EmploymentStatus::Activo)
        ->and($employee->area)->toBe('administrativo')
        ->and($employee->area_specific)->toBe('oficios_varios')
        ->and($employee->contract_type)->toBe('indefinido')
        ->and($employee->sex)->toBe('M')
        ->and($employee->has_children)->toBeTrue()
        ->and($employee->email)->toBe('dejala.vi@gmail.com')
        ->and($employee->phone)->toBe('3214916527')
        ->and($employee->birth_date->toDateString())->toBe('1984-02-08')
        ->and($employee->hire_date->toDateString())->toBe('2022-08-04')
        ->and($employee->document_issue_place)->toBe('MANI-CASANARE')
        ->and($employee->emergency_contact_phone)->toBe('3214774592')
        ->and($employee->blood_type)->toBe('A+')
        ->and($employee->city)->toBe('Mani-Casanare')
        ->and($employee->arl)->toBe('SEGUROS BOLÍVAR')
        ->and($employee->pants_size)->toBe('14')
        ->and($employee->jacket_size)->toBe('XL');
});

it('el inactivo entra como retirado, no se pierde', function (): void {
    $file = rosterWorkbook([
        [2, 19122021, 'INACTIVO', 'Ana Elsa Gil Lopez', 52503861, 'Operario de Proceso I', 'OPERATIVO', 'PROCESOS'],
    ]);

    $this->artisan('hr:import-roster', ['file' => $file, '--tenant' => $this->tenant->id])->assertSuccessful();

    expect(Employee::withoutGlobalScopes()->where('document_number', '52503861')->sole()->status)
        ->toBe(EmploymentStatus::Retirado);
});

it('una celda vacía no borra lo que la ficha ya tenía, y el salario no se toca', function (): void {
    $existing = Employee::factory()->create([
        'tenant_id' => $this->tenant->id,
        'document_number' => '74811360',
        'base_salary' => 1_750_905,
        'email' => 'ya.estaba@example.com',
        'blood_type' => 'O+',
    ]);

    $file = rosterWorkbook([
        [1, 'O4092021', 'ACTIVO', 'Fermin Beltran Vergara', 74811360, 'Auxiliar de Laboratorio', 'OPERATIVO', 'LABORATORIO'],
    ]);

    $this->artisan('hr:import-roster', ['file' => $file, '--tenant' => $this->tenant->id])->assertSuccessful();

    $existing->refresh();

    expect($existing->position)->toBe('Auxiliar de Laboratorio')
        ->and($existing->email)->toBe('ya.estaba@example.com')
        ->and($existing->blood_type)->toBe('O+')
        ->and((float) $existing->base_salary)->toBe(1_750_905.0)
        ->and(Employee::withoutGlobalScopes()->count())->toBe(1);
});

it('el ensayo no escribe nada', function (): void {
    $file = rosterWorkbook([
        [1, 'O4092021', 'ACTIVO', 'Fermin Beltran Vergara', 74811360, 'Auxiliar de Laboratorio'],
    ]);

    $this->artisan('hr:import-roster', ['file' => $file, '--tenant' => $this->tenant->id, '--dry-run' => true])
        ->expectsOutputToContain('Ensayo')
        ->assertSuccessful();

    expect(Employee::withoutGlobalScopes()->count())->toBe(0);
});

it('falla con claridad si la hoja no existe', function (): void {
    $file = rosterWorkbook([]);

    $this->artisan('hr:import-roster', ['file' => $file, '--tenant' => $this->tenant->id, '--sheet' => 'Otra'])
        ->expectsOutputToContain('no tiene la hoja')
        ->assertFailed();
});
