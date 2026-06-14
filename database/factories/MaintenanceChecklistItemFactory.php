<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\MaintenanceChecklistItemType;
use App\Models\MaintenanceChecklistItem;
use App\Models\MaintenancePlanTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceChecklistItem>
 */
class MaintenanceChecklistItemFactory extends Factory
{
    public function definition(): array
    {
        $task = MaintenancePlanTask::factory()->create();

        return [
            'tenant_id' => $task->tenant_id,
            'maintenance_plan_task_id' => $task->id,
            'sort_order' => $this->faker->numberBetween(1, 10),
            'label' => $this->faker->sentence(5).'?',
            'item_type' => MaintenanceChecklistItemType::Boolean->value,
            'unit' => null,
            'expected_min' => null,
            'expected_max' => null,
            'is_required' => true,
        ];
    }

    public function numeric(string $unit = '°C', float $min = 60.0, float $max = 80.0): static
    {
        return $this->state(fn () => [
            'item_type' => MaintenanceChecklistItemType::Numeric->value,
            'unit' => $unit,
            'expected_min' => $min,
            'expected_max' => $max,
        ]);
    }

    public function text(): static
    {
        return $this->state(fn () => [
            'item_type' => MaintenanceChecklistItemType::Text->value,
        ]);
    }
}
