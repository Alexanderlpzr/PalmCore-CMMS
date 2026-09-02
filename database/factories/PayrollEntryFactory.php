<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollEntry>
 */
class PayrollEntryFactory extends Factory
{
    protected $model = PayrollEntry::class;

    public function definition(): array
    {
        $run = PayrollRun::factory()->create();
        $employee = Employee::factory()->create(['tenant_id' => $run->tenant_id]);

        return [
            'tenant_id' => $run->tenant_id,
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->fullName(),
            'document_number' => $employee->document_number,
            'position' => $employee->position,
            'base_salary' => $employee->base_salary,
            'day_value' => 58363.5,
            'hour_value' => 7958.66,
            'worked_days' => 30,
            'total_days' => 30,
            'net_pay' => 1750905,
        ];
    }

    public function forRun(PayrollRun $run, Employee $employee): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $run->tenant_id,
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->fullName(),
            'document_number' => $employee->document_number,
            'base_salary' => $employee->base_salary,
        ]);
    }
}
