<?php

namespace Database\Factories;

use App\Domain\HumanResources\Enums\NoveltyType;
use App\Models\Employee;
use App\Models\EmployeeNovelty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeNovelty>
 */
class EmployeeNoveltyFactory extends Factory
{
    protected $model = EmployeeNovelty::class;

    public function definition(): array
    {
        $employee = Employee::factory()->create();

        return [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'type' => NoveltyType::Vacaciones,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->toDateString(),
            'reference' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
        ]);
    }

    public function of(NoveltyType $type, string $from, string $to): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
            'starts_on' => $from,
            'ends_on' => $to,
        ]);
    }
}
