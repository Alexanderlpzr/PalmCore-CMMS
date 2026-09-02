<?php

use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Exceptions\AttendanceException;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/*
 * Los casos de esta suite son los que de verdad ocurren en una puerta de planta: el
 * vigilante que pasa el carné dos veces porque la pantalla tarda, el operario que se fue
 * el viernes sin marcar la salida, y el carné del que ya no trabaja ahí.
 */

function attendance(): AttendanceService
{
    return app(AttendanceService::class);
}

function employeeWithCard(?Tenant $tenant = null): array
{
    $tenant ??= Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $card = EmployeeQrCode::factory()->forEmployee($employee)->create();

    return [$tenant, $employee, $card];
}

it('la primera marca del trabajador es una entrada', function (): void {
    [, , $card] = employeeWithCard();

    $scan = attendance()->record($card);

    expect($scan->direction)->toBe(AttendanceDirection::Entrada);
});

it('alterna el sentido en la marca siguiente', function (): void {
    [, , $card] = employeeWithCard();

    $entrada = attendance()->record($card, at: Carbon::parse('2026-08-10 06:00'));
    $salida = attendance()->record($card, at: Carbon::parse('2026-08-10 14:00'));

    expect($entrada->direction)->toBe(AttendanceDirection::Entrada)
        ->and($salida->direction)->toBe(AttendanceDirection::Salida);
});

it('trata dos pases seguidos del mismo carné como uno solo', function (): void {
    [, $employee, $card] = employeeWithCard();

    // La pantalla tarda, el vigilante no ve confirmación y vuelve a pasar el carné.
    $primero = attendance()->record($card, at: Carbon::parse('2026-08-10 06:00:00'));
    $segundo = attendance()->record($card, at: Carbon::parse('2026-08-10 06:00:20'));

    expect($segundo->id)->toBe($primero->id)
        ->and($employee->attendanceScans()->count())->toBe(1);
});

it('registra una entrada nueva cuando la anterior quedó abierta demasiado tiempo', function (): void {
    [, , $card] = employeeWithCard();

    // Se fue el viernes sin marcar la salida y vuelve el lunes.
    attendance()->record($card, at: Carbon::parse('2026-08-07 06:00'));
    $lunes = attendance()->record($card, at: Carbon::parse('2026-08-10 06:00'));

    expect($lunes->direction)->toBe(AttendanceDirection::Entrada)
        ->and($lunes->notes)->toContain('quedó sin salida');
});

it('cierra el turno normal que cruza la medianoche', function (): void {
    [, , $card] = employeeWithCard();

    // Turno de noche: entra el sábado a las 22:00 y sale el domingo a las 06:00. Es el
    // caso que en el libro de Excel aparece anotado en el día en que arrancó el turno.
    $entrada = attendance()->record($card, at: Carbon::parse('2026-08-22 22:00'));
    $salida = attendance()->record($card, at: Carbon::parse('2026-08-23 06:00'));

    expect($entrada->direction)->toBe(AttendanceDirection::Entrada)
        ->and($salida->direction)->toBe(AttendanceDirection::Salida)
        ->and($salida->notes)->toBeNull();
});

it('rechaza el carné de quien ya no trabaja ahí', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->retired()->create(['tenant_id' => $tenant->id]);
    $card = EmployeeQrCode::factory()->forEmployee($employee)->create();

    attendance()->resolveToken($card->qr_token, $tenant->id);
})->throws(AttendanceException::class);

it('rechaza un carné anulado', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $card = EmployeeQrCode::factory()->forEmployee($employee)->inactive()->create();

    attendance()->resolveToken($card->qr_token, $tenant->id);
})->throws(AttendanceException::class);

it('no resuelve el carné de otra empresa', function (): void {
    [, , $card] = employeeWithCard();
    $otro = Tenant::factory()->create();

    attendance()->resolveToken($card->qr_token, $otro->id);
})->throws(AttendanceException::class);

it('cuenta los escaneos del carné', function (): void {
    [, , $card] = employeeWithCard();

    attendance()->record($card, at: Carbon::parse('2026-08-10 06:00'));
    attendance()->record($card, at: Carbon::parse('2026-08-10 14:00'));

    expect($card->fresh()->scan_count)->toBe(2);
});

it('lista las entradas que nunca se cerraron', function (): void {
    $tenant = Tenant::factory()->create();
    [, $olvidadizo, $cardUno] = employeeWithCard($tenant);
    [, , $cardDos] = employeeWithCard($tenant);

    // Uno entra y no sale; el otro cumple.
    attendance()->record($cardUno, at: Carbon::parse('2026-08-10 06:00'));
    attendance()->record($cardDos, at: Carbon::parse('2026-08-10 06:00'));
    attendance()->record($cardDos, at: Carbon::parse('2026-08-10 14:00'));

    $abiertas = attendance()->openEntries(
        $tenant->id,
        Carbon::parse('2026-08-10 00:00'),
        Carbon::parse('2026-08-10 23:59'),
    );

    expect($abiertas)->toHaveCount(1)
        ->and($abiertas->first()->employee_id)->toBe($olvidadizo->id);
});
