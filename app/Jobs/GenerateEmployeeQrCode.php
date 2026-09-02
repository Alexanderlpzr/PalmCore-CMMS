<?php

namespace App\Jobs;

use App\Domain\HumanResources\Services\EmployeeQrCodeService;
use App\Models\Employee;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Emite el carné del trabajador recién creado, para que RRHH no tenga que pedirlo.
 */
class GenerateEmployeeQrCode implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public readonly Employee $employee) {}

    public function handle(EmployeeQrCodeService $service): void
    {
        // Sin scopes globales: el worker no tiene contexto de tenant, así que el scope
        // filtraría por null y no vería el carné que ya existe.
        if ($this->employee->qrCodes()->withoutGlobalScopes()->where('is_active', true)->exists()) {
            return;
        }

        $service->createForEmployee($this->employee);
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('Falló la generación del carné QR del empleado', [
            'employee_id' => $this->employee->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
