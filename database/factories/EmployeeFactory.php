<?php

namespace Database\Factories;

use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plant_id' => null,
            'user_id' => null,
            'document_type' => 'CC',
            'document_number' => (string) fake()->unique()->numberBetween(1_000_000_00, 1_999_999_99),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'position' => 'Operario de Proceso I',
            'base_salary' => 1_750_905,
            'salary_type' => 'ordinario',
            'excluded_from_overtime' => false,
            'transport_allowance_override' => null,
            'hire_date' => now()->subYear()->toDateString(),
            'termination_date' => null,
            'status' => EmploymentStatus::Activo,
            'eps' => null,
            'pension_fund' => null,
            'severance_fund' => null,
            'arl_risk_class' => null,
            'notes' => null,
        ];
    }

    /** Dirección, confianza y manejo: no causa horas extras ni recargos. */
    public function supervisor(): static
    {
        return $this->state(fn (): array => [
            'position' => 'Supervisor',
            'base_salary' => 3_551_508,
            'excluded_from_overtime' => true,
        ]);
    }

    public function retired(): static
    {
        return $this->state(fn (): array => [
            'status' => EmploymentStatus::Retirado,
            'termination_date' => now()->subMonth()->toDateString(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => EmploymentStatus::Suspendido]);
    }
}
