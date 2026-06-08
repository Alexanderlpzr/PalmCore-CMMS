<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTimeLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderTimeLog>
 */
class WorkOrderTimeLogFactory extends Factory
{
    public function definition(): array
    {
        $workOrder = WorkOrder::factory()->create();
        $startedAt = now()->subHours($this->faker->numberBetween(1, 8));
        $endedAt   = (clone $startedAt)->addHours($this->faker->numberBetween(1, 4));

        return [
            'tenant_id'     => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'user_id'       => User::factory()->create()->id,
            'started_at'    => $startedAt,
            'ended_at'      => $endedAt,
            'hours'         => round($startedAt->diffInMinutes($endedAt) / 60, 2),
            'description'   => $this->faker->optional()->sentence(8),
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'ended_at' => null,
            'hours'    => null,
        ]);
    }
}
