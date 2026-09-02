<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeDeduction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDeduction>
 */
class EmployeeDeductionFactory extends Factory
{
    protected $model = EmployeeDeduction::class;

    public function definition(): array
    {
        $employee = Employee::factory()->create();

        return [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'concept' => 'Seguro funerario',
            'amount' => 12750,
            'effective_from' => now()->startOfYear()->toDateString(),
            'effective_to' => null,
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
}
