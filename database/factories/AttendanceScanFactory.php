<?php

namespace Database\Factories;

use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Models\AttendanceScan;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceScan>
 */
class AttendanceScanFactory extends Factory
{
    protected $model = AttendanceScan::class;

    public function definition(): array
    {
        $employee = Employee::factory()->create();

        return [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'employee_qr_code_id' => null,
            'scanned_at' => now(),
            'direction' => AttendanceDirection::Entrada,
            'source' => 'qr',
            'recorded_by' => null,
            'gate' => 'Portería principal',
            'notes' => null,
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
        ]);
    }

    public function exit(): static
    {
        return $this->state(fn (): array => ['direction' => AttendanceDirection::Salida]);
    }
}
