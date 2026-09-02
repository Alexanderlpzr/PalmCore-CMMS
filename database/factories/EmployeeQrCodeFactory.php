<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeQrCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmployeeQrCode>
 */
class EmployeeQrCodeFactory extends Factory
{
    protected $model = EmployeeQrCode::class;

    public function definition(): array
    {
        $employee = Employee::factory()->create();

        return [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'qr_token' => (string) Str::uuid(),
            'qr_image_path' => null,
            'is_active' => true,
            'generated_at' => now(),
            'last_scanned_at' => null,
            'scan_count' => 0,
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
