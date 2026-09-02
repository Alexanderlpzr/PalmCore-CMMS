<?php

use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollConcept;
use App\Models\PayrollParameterVersion;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Spatie\Permission\PermissionRegistrar;

/*
 * La nómina es la primera información del sistema que el administrador del tenant no
 * debe ver. Esta suite es la que impide que alguien, con toda la buena intención,
 * «arregle» el módulo dándole los permisos a administrador-general.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);

    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
});

function payrollUserWithRole(string $role, Tenant $tenant): User
{
    $user = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    return $user->fresh();
}

it('el administrador general no ve el sueldo de nadie', function (): void {
    $admin = payrollUserWithRole('administrador-general', $this->tenant);

    expect($admin->can('viewSalary', $this->employee))->toBeFalse();
});

it('el administrador general tampoco entra al maestro de personal ni a los parámetros', function (): void {
    $admin = payrollUserWithRole('administrador-general', $this->tenant);

    expect($admin->can('viewAny', Employee::class))->toBeFalse()
        ->and($admin->can('viewAny', PayrollParameterVersion::class))->toBeFalse()
        ->and($admin->can('viewAny', PayrollConcept::class))->toBeFalse()
        ->and($admin->can('viewAny', Holiday::class))->toBeFalse();
});

it('talento humano ve el sueldo y administra los parámetros', function (): void {
    $rrhh = payrollUserWithRole('talento-humano', $this->tenant);

    expect($rrhh->can('viewSalary', $this->employee))->toBeTrue()
        ->and($rrhh->can('viewAny', Employee::class))->toBeTrue()
        ->and($rrhh->can('create', PayrollParameterVersion::class))->toBeTrue()
        ->and($rrhh->can('create', PayrollConcept::class))->toBeTrue()
        ->and($rrhh->can('create', Holiday::class))->toBeTrue();
});

it('la comprobación de sueldo sin empleado delante funciona igual que con uno', function (): void {
    /*
     * Es la que usan la columna de la tabla y el formulario al crear. `Gate` descarta el
     * nombre de clase que se le pasa, así que `viewSalary` —que exige un Employee— no se
     * puede invocar así: Laravel devuelve null, o sea deniega, sin lanzar ningún error.
     * La columna del sueldo quedaría invisible incluso para talento humano y nada lo
     * delataría. Por eso existe `viewAnySalary`, y por eso se prueba.
     */
    $rrhh = payrollUserWithRole('talento-humano', $this->tenant);
    $admin = payrollUserWithRole('administrador-general', $this->tenant);

    expect($rrhh->can('viewAnySalary', Employee::class))->toBeTrue()
        ->and($admin->can('viewAnySalary', Employee::class))->toBeFalse();
});

it('portería identifica al trabajador pero no ve su sueldo', function (): void {
    $porteria = payrollUserWithRole('porteria', $this->tenant);

    expect($porteria->can('view', $this->employee))->toBeTrue()
        ->and($porteria->can('viewSalary', $this->employee))->toBeFalse()
        ->and($porteria->can('create', AttendanceScan::class))->toBeTrue();
});

it('portería no puede editar al trabajador que escanea', function (): void {
    $porteria = payrollUserWithRole('porteria', $this->tenant);

    expect($porteria->can('update', $this->employee))->toBeFalse()
        ->and($porteria->can('create', Employee::class))->toBeFalse();
});

it('talento humano no puede marcar entradas en portería', function (): void {
    // Separación de funciones: quien liquida la nómina no registra la asistencia.
    $rrhh = payrollUserWithRole('talento-humano', $this->tenant);

    expect($rrhh->can('create', AttendanceScan::class))->toBeFalse();
});

it('ningún rol del tenant puede editar ni borrar una marca de portería', function (): void {
    // Se corrige registrando otra marca, con su nota y su autor. El superadministrador de
    // plataforma sí pasa, por el `Gate::before` que es anterior a este módulo.
    $scan = AttendanceScan::factory()->forEmployee($this->employee)->create();

    foreach (['administrador-general', 'talento-humano', 'porteria'] as $role) {
        $user = payrollUserWithRole($role, $this->tenant);

        expect($user->can('update', $scan))->toBeFalse()
            ->and($user->can('delete', $scan))->toBeFalse();
    }
});

it('marcar el reloj y firmar las horas son facultades separadas', function (): void {
    // Portería registra que alguien cruzó la puerta, y eso es un hecho. Firmar las horas
    // es afirmar que son las que se van a pagar, y eso es una decisión. Quien marca no firma.
    $porteria = payrollUserWithRole('porteria', $this->tenant);
    $rrhh = payrollUserWithRole('talento-humano', $this->tenant);

    $dia = AttendanceDay::factory()->forEmployee($this->employee)->create();

    expect($porteria->can('confirm', $dia))->toBeFalse()
        ->and($rrhh->can('confirm', $dia))->toBeTrue()
        ->and($porteria->can('create', AttendanceScan::class))->toBeTrue()
        ->and($rrhh->can('create', AttendanceScan::class))->toBeFalse();
});

it('el administrador general no ve ni firma las horas del reloj', function (): void {
    $admin = payrollUserWithRole('administrador-general', $this->tenant);
    $dia = AttendanceDay::factory()->forEmployee($this->employee)->create();

    expect($admin->can('viewAny', AttendanceDay::class))->toBeFalse()
        ->and($admin->can('confirm', $dia))->toBeFalse();
});

it('un día de asistencia no se escribe ni se edita a mano', function (): void {
    // Se deriva de las marcas. Editarlo dejaría horas que no corresponden a ningún
    // escaneo, que es justo el problema que el módulo vino a resolver.
    $rrhh = payrollUserWithRole('talento-humano', $this->tenant);
    $dia = AttendanceDay::factory()->forEmployee($this->employee)->create();

    expect($rrhh->can('create', AttendanceDay::class))->toBeFalse()
        ->and($rrhh->can('update', $dia))->toBeFalse()
        ->and($rrhh->can('delete', $dia))->toBeFalse();
});

it('no se puede firmar dos veces ni reabrir lo que no está firmado', function (): void {
    $rrhh = payrollUserWithRole('talento-humano', $this->tenant);

    $propuesta = AttendanceDay::factory()->forEmployee($this->employee)->create();
    $firmada = AttendanceDay::factory()->forEmployee($this->employee)->confirmed()->create([
        'work_date' => now()->subDay()->toDateString(),
    ]);

    expect($rrhh->can('confirm', $propuesta))->toBeTrue()
        ->and($rrhh->can('reopen', $propuesta))->toBeFalse()
        ->and($rrhh->can('confirm', $firmada))->toBeFalse()
        ->and($rrhh->can('reopen', $firmada))->toBeTrue();
});

it('un tramo de vigencia ya cerrado no se puede modificar', function (): void {
    $rrhh = payrollUserWithRole('talento-humano', $this->tenant);

    $cerrado = PayrollParameterVersion::factory()
        ->closed('2026-01-01', '2026-06-30')
        ->create(['tenant_id' => $this->tenant->id]);

    $abierto = PayrollParameterVersion::factory()
        ->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2026-07-01']);

    expect($rrhh->can('update', $cerrado))->toBeFalse()
        ->and($rrhh->can('delete', $cerrado))->toBeFalse()
        ->and($rrhh->can('update', $abierto))->toBeTrue();
});
