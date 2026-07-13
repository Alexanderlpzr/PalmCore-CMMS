<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\MaintenanceChecklistItemType;
use App\Models\WorkOrderChecklistResult;
use App\Models\WorkOrderTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderChecklistResult>
 */
class WorkOrderChecklistResultFactory extends Factory
{
    public function definition(): array
    {
        $task = WorkOrderTask::factory()->create();

        return [
            'tenant_id' => $task->tenant_id,
            'work_order_task_id' => $task->id,
            'maintenance_checklist_item_id' => null,
            'sort_order' => $this->faker->numberBetween(1, 10),
            'label' => $this->faker->sentence(5),
            'item_type' => MaintenanceChecklistItemType::Boolean->value,
            'unit' => null,
            'expected_min' => null,
            'expected_max' => null,
            'is_required' => true,
            'value_boolean' => null,
            'value_numeric' => null,
            'value_text' => null,
            'photo_path' => null,
            'notes' => null,
            'recorded_at' => null,
            'recorded_by' => null,
        ];
    }

    /** A numeric item with a tolerance band, e.g. vibración 2.0–7.1 mm/s. */
    public function numeric(float $min = 2.0, float $max = 7.1, string $unit = 'mm/s'): static
    {
        return $this->state(fn (): array => [
            'item_type' => MaintenanceChecklistItemType::Numeric->value,
            'unit' => $unit,
            'expected_min' => $min,
            'expected_max' => $max,
        ]);
    }

    public function optional(): static
    {
        return $this->state(fn (): array => ['is_required' => false]);
    }
}
