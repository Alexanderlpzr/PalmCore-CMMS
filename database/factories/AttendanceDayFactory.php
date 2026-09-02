<?php

namespace Database\Factories;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Models\AttendanceDay;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceDay>
 */
class AttendanceDayFactory extends Factory
{
    protected $model = AttendanceDay::class;

    public function definition(): array
    {
        $employee = Employee::factory()->create();

        return [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'work_date' => now()->toDateString(),
            'ordinary_hours' => 8,
            'night_surcharge_hours' => 0,
            'sunday_surcharge_hours' => 0,
            'night_sunday_surcharge_hours' => 0,
            'overtime_day_hours' => 0,
            'overtime_night_hours' => 0,
            'overtime_sunday_day_hours' => 0,
            'overtime_sunday_night_hours' => 0,
            'worked_hours' => 8,
            'status' => AttendanceDayStatus::Propuesta,
            'built_at' => now(),
            'source' => 'qr',
            'anomalies' => null,
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => AttendanceDayStatus::Confirmada,
            'confirmed_at' => now(),
        ]);
    }
}
