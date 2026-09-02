<?php

use App\Models\Employee;
use App\Models\EmployeeQrCode;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Spatie\Permission\PermissionRegistrar;

/*
 * El endpoint de la puerta. Lo que se protege aquí es que el carné identifique sin
 * autorizar: a diferencia del QR de equipos, que abre una página pública, este exige
 * sesión de portería.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);

    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->employee = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->card = EmployeeQrCode::factory()->forEmployee($this->employee)->create();
});

function porteriaToken(Tenant $tenant, array $abilities = ['*']): string
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole('porteria');

    $result = $user->createToken('porteria-test', $abilities);
    $result->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return $result->plainTextToken;
}

function porteriaHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

it('registra la entrada y responde con el nombre para que portería confirme', function (): void {
    $token = porteriaToken($this->tenant);

    $response = $this->postJson(
        route('api.v1.attendance.scan'),
        ['qr_token' => $this->card->qr_token, 'gate' => 'Portería principal'],
        porteriaHeaders($token),
    );

    $response->assertCreated()
        ->assertJsonPath('data.direction', 'entrada')
        ->assertJsonPath('data.employee.full_name', $this->employee->fullName())
        ->assertJsonPath('data.gate', 'Portería principal');
});

it('nunca devuelve el sueldo del trabajador', function (): void {
    $token = porteriaToken($this->tenant);

    $response = $this->postJson(
        route('api.v1.attendance.scan'),
        ['qr_token' => $this->card->qr_token],
        porteriaHeaders($token),
    );

    $response->assertCreated();
    expect($response->json('data.employee'))->not->toHaveKey('base_salary');
});

it('exige sesión: el token del carné por sí solo no marca nada', function (): void {
    $this->postJson(
        route('api.v1.attendance.scan'),
        ['qr_token' => $this->card->qr_token],
    )->assertUnauthorized();
});

it('rechaza al usuario cuyo token no tiene la facultad de marcar', function (): void {
    $token = porteriaToken($this->tenant, ['attendance.read']);

    $this->postJson(
        route('api.v1.attendance.scan'),
        ['qr_token' => $this->card->qr_token],
        porteriaHeaders($token),
    )->assertForbidden();
});

it('explica por qué no puede marcar quien ya no trabaja ahí', function (): void {
    $retirado = Employee::factory()->retired()->create(['tenant_id' => $this->tenant->id]);
    $carne = EmployeeQrCode::factory()->forEmployee($retirado)->create();
    $token = porteriaToken($this->tenant);

    // 422 y no 404: el carné existe, y portería necesita leer el motivo en pantalla.
    $this->postJson(
        route('api.v1.attendance.scan'),
        ['qr_token' => $carne->qr_token],
        porteriaHeaders($token),
    )->assertStatus(422);
});

it('no deja marcar con el carné de otra empresa', function (): void {
    $otro = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($otro);
    $ajeno = Employee::factory()->create(['tenant_id' => $otro->id]);
    $carneAjeno = EmployeeQrCode::factory()->forEmployee($ajeno)->create();

    $token = porteriaToken($this->tenant);

    $this->postJson(
        route('api.v1.attendance.scan'),
        ['qr_token' => $carneAjeno->qr_token],
        porteriaHeaders($token),
    )->assertStatus(422);
});

it('valida que el token tenga forma de carné', function (): void {
    $token = porteriaToken($this->tenant);

    $this->postJson(
        route('api.v1.attendance.scan'),
        ['qr_token' => 'no-es-un-uuid'],
        porteriaHeaders($token),
    )->assertStatus(422);
});

it('lista las marcas del día', function (): void {
    $token = porteriaToken($this->tenant);

    $this->postJson(
        route('api.v1.attendance.scan'),
        ['qr_token' => $this->card->qr_token],
        porteriaHeaders($token),
    )->assertCreated();

    $this->getJson(route('api.v1.attendance.index'), porteriaHeaders($token))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.employee_name', $this->employee->fullName());
});
