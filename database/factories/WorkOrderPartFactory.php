<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderPart>
 */
class WorkOrderPartFactory extends Factory
{
    public function definition(): array
    {
        $workOrder = WorkOrder::factory()->create();
        $quantity  = $this->faker->randomFloat(2, 1, 20);
        $unitCost  = $this->faker->optional(0.8)->randomFloat(2, 500, 50000);

        return [
            'tenant_id'     => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'part_code'     => $this->faker->optional()->bothify('REP-####'),
            'description'   => $this->faker->words(3, true),
            'quantity'      => $quantity,
            'unit'          => $this->faker->randomElement(['pcs', 'kg', 'L', 'm', 'un']),
            'unit_cost'     => $unitCost,
            'total_cost'    => $unitCost !== null ? round($quantity * $unitCost, 2) : null,
        ];
    }
}
