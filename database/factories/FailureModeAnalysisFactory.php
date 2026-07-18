<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\FailureConsequenceCategory;
use App\Domain\Maintenance\Enums\FailureMode;
use App\Models\Equipment;
use App\Models\FailureModeAnalysis;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FailureModeAnalysis>
 */
class FailureModeAnalysisFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

        return [
            'tenant_id' => $tenant->id,
            'equipment_id' => $equipment->id,
            'equipment_component_id' => null,
            'failure_mode' => $this->faker->randomElement(FailureMode::cases())->value,
            'consequence_category' => $this->faker->randomElement(FailureConsequenceCategory::cases())->value,
            'effect_description' => $this->faker->optional()->sentence(),
            'failure_finding_plan_id' => null,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'consequence_category' => FailureConsequenceCategory::Hidden->value,
        ]);
    }
}
