<?php

namespace App\Jobs;

use App\Domain\HumanResources\Exceptions\PayrollParameterException;
use App\Domain\HumanResources\Services\AttendanceDayBuilder;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Calcula las horas del turno que acaba de cerrar una salida en portería.
 *
 * Va en cola y no dentro del escaneo: en el cambio de turno pasan cuarenta personas en
 * diez minutos, y la puerta no puede esperar a que se clasifiquen las horas de cada una.
 * Si algo falla aquí, la marca ya quedó guardada y «Reconstruir período» lo rehace.
 */
class BuildAttendanceDaysJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $employeeId,
        public string $from,
        public string $to,
    ) {}

    public function handle(AttendanceDayBuilder $builder): void
    {
        $employee = Employee::query()->acrossAllTenants()->find($this->employeeId);

        if ($employee === null) {
            return;
        }

        try {
            $builder->buildForEmployee($employee, CarbonImmutable::parse($this->from), CarbonImmutable::parse($this->to));
        } catch (PayrollParameterException $e) {
            // Sin parámetros de nómina cargados no hay con qué clasificar las horas. No es
            // una falla del trabajo: las marcas esperan, y al cargarlos se reconstruye.
            Log::info('Horas sin calcular: faltan parámetros de nómina.', [
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'motivo' => $e->getMessage(),
            ]);
        }
    }
}
