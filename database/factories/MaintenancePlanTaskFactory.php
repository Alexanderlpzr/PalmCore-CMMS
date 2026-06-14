<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenancePlanTask>
 */
class MaintenancePlanTaskFactory extends Factory
{
    public function definition(): array
    {
        $plan = MaintenancePlan::factory()->create();

        return [
            'tenant_id' => $plan->tenant_id,
            'maintenance_plan_id' => $plan->id,
            'sort_order' => $this->faker->numberBetween(1, 20),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(2),
            'estimated_minutes' => $this->faker->optional()->numberBetween(5, 120),
        ];
    }
}
