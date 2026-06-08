<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderComment>
 */
class WorkOrderCommentFactory extends Factory
{
    public function definition(): array
    {
        $workOrder = WorkOrder::factory()->create();

        return [
            'tenant_id'     => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'user_id'       => User::factory()->create()->id,
            'body'          => $this->faker->paragraph(2),
            'is_internal'   => false,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn () => ['is_internal' => true]);
    }
}
