<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTechnician;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderTechnician>
 */
class WorkOrderTechnicianFactory extends Factory
{
    public function definition(): array
    {
        $workOrder = WorkOrder::factory()->create();

        return [
            'tenant_id'     => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'user_id'       => User::factory()->create()->id,
            'role'          => TechnicianRole::Technician->value,
            'planned_hours' => $this->faker->optional()->randomFloat(1, 1, 16),
            'hourly_rate'   => $this->faker->optional()->randomFloat(2, 15000, 80000),
            'notes'         => null,
        ];
    }

    public function lead(): static
    {
        return $this->state(fn () => ['role' => TechnicianRole::Lead->value]);
    }
}
