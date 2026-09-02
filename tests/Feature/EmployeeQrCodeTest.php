<?php

use App\Domain\HumanResources\Exceptions\AttendanceException;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Domain\HumanResources\Services\EmployeeQrCodeService;
use App\Jobs\GenerateEmployeeQrCode;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

function qrService(): EmployeeQrCodeService
{
    return app(EmployeeQrCodeService::class);
}

it('emite el carné del trabajador recién creado', function (): void {
    $employee = Employee::factory()->create();

    (new GenerateEmployeeQrCode($employee))->handle(qrService());

    $card = $employee->qrCodes()->first();

    expect($card)->not->toBeNull()
        ->and($card->is_active)->toBeTrue()
        ->and($card->qr_token)->toBeString();

    Storage::disk(persistent_disk())->assertExists($card->qr_image_path);
});

it('no emite un segundo carné si el trabajador ya tiene uno activo', function (): void {
    $employee = Employee::factory()->create();
    EmployeeQrCode::factory()->forEmployee($employee)->create();

    (new GenerateEmployeeQrCode($employee))->handle(qrService());

    expect($employee->qrCodes()->count())->toBe(1);
});

it('el código lleva el token pelado y no una URL', function (): void {
    // El QR del equipo apunta a una página pública; este no debe abrir nada si alguien
    // lo fotografía en la puerta.
    $employee = Employee::factory()->create();

    $card = qrService()->createForEmployee($employee);

    expect($card->qr_token)->not->toContain('http');
});

it('reemitir el carné invalida el anterior de inmediato', function (): void {
    $tenant = Tenant::factory()->create();
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id]);
    $viejo = qrService()->createForEmployee($employee);

    $nuevo = qrService()->regenerate($viejo);

    expect($nuevo->id)->not->toBe($viejo->id)
        ->and($nuevo->is_active)->toBeTrue()
        ->and($viejo->fresh()->is_active)->toBeFalse();

    // Y lo importante: el carné perdido ya no sirve para marcar.
    expect(fn () => app(AttendanceService::class)->resolveToken($viejo->qr_token, $tenant->id))
        ->toThrow(AttendanceException::class);
});

it('cada carné usa un token distinto', function (): void {
    $tokens = collect(range(1, 5))->map(fn (): string => qrService()->generateToken());

    expect($tokens->unique())->toHaveCount(5);
});
