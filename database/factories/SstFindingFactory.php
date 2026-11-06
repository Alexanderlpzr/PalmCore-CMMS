<?php

namespace Database\Factories;

use App\Domain\Safety\Enums\FindingPriority;
use App\Domain\Safety\Enums\FindingStatus;
use App\Models\SstFinding;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SstFinding>
 */
class SstFindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $priority = $this->faker->randomElement(FindingPriority::cases());
        $reportedOn = now()->subDays($this->faker->numberBetween(0, 20))->startOfDay();

        return [
            'tenant_id' => Tenant::factory(),
            'number' => $this->faker->unique()->numberBetween(1, 99_999),
            'reported_on' => $reportedOn,
            'description' => $this->faker->sentence(),
            'location_detail' => $this->faker->optional()->words(2, true),
            'priority' => $priority->value,
            'action_plan' => $this->faker->sentence(),
            'due_on' => $reportedOn->copy()->addDays($priority->days()),
            'status' => FindingStatus::Pendiente->value,
        ];
    }
}
