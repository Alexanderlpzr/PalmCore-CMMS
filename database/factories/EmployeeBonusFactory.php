<?php

namespace Database\Factories;

use App\Domain\HumanResources\Enums\BonusType;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeBonus>
 */
class EmployeeBonusFactory extends Factory
{
    protected $model = EmployeeBonus::class;

    public function definition(): array
    {
        $employee = Employee::factory()->create();

        return [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'type' => BonusType::Constitutiva,
            'concept' => 'Bonificación de producción',
            'amount' => 100000,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'effective_to' => now()->endOfMonth()->toDateString(),
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

    public function of(BonusType $type, float $amount, string $from, string $to): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
            'amount' => $amount,
            'effective_from' => $from,
            'effective_to' => $to,
        ]);
    }
}
