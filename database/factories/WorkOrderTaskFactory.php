<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\WorkOrderTaskStatus;
use App\Models\WorkOrder;
use App\Models\WorkOrderTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderTask>
 */
class WorkOrderTaskFactory extends Factory
{
    public function definition(): array
    {
        $workOrder = WorkOrder::factory()->create();

        return [
            'tenant_id' => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'maintenance_plan_task_id' => null,
            'sort_order' => $this->faker->numberBetween(1, 20),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(2),
            'estimated_minutes' => $this->faker->optional()->numberBetween(5, 120),
            'status' => WorkOrderTaskStatus::Pending->value,
            'skipped_reason' => null,
            'assigned_to' => null,
            'started_at' => null,
            'completed_at' => null,
            'completed_by' => null,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (): array => [
            'status' => WorkOrderTaskStatus::Done->value,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }

    public function skipped(string $reason = 'Sin repuesto en almacén'): static
    {
        return $this->state(fn (): array => [
            'status' => WorkOrderTaskStatus::Skipped->value,
            'skipped_reason' => $reason,
            'completed_at' => now(),
        ]);
    }
}
