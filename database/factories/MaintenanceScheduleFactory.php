<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use App\Models\MaintenanceSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceSchedule>
 */
class MaintenanceScheduleFactory extends Factory
{
    public function definition(): array
    {
        $plan = MaintenancePlan::factory()->create();

        return [
            'tenant_id' => $plan->tenant_id,
            'maintenance_plan_id' => $plan->id,
            'last_completed_at' => null,
            'last_completed_meter' => null,
            'next_due_at' => now()->addMonth(),
            'next_due_meter' => null,
            'times_executed' => 0,
            'times_skipped' => 0,
            'last_work_order_id' => null,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'next_due_at' => now()->subDays(5),
            'times_executed' => 3,
            'last_completed_at' => now()->subMonth()->subDays(5),
        ]);
    }

    public function withMeterDue(float $nextMeter = 500.0): static
    {
        return $this->state(fn () => [
            'next_due_meter' => $nextMeter,
            'last_completed_meter' => $nextMeter - 500,
        ]);
    }
}
