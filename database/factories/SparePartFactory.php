<?php

namespace Database\Factories;

use App\Domain\Inventory\Enums\SparePartAbcClassification;
use App\Domain\Inventory\Enums\SparePartCategoryType;
use App\Domain\Inventory\Enums\SparePartCriticality;
use App\Domain\Inventory\Enums\SparePartUnit;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SparePart>
 */
class SparePartFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'code' => strtoupper($this->faker->unique()->bothify('SP-###??')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'category_type' => $this->faker->randomElement(SparePartCategoryType::cases())->value,
            'criticality' => $this->faker->randomElement(SparePartCriticality::cases())->value,
            'abc_classification' => $this->faker->randomElement(SparePartAbcClassification::cases())->value,
            'unit' => SparePartUnit::Piece->value,
            'unit_cost' => $this->faker->randomFloat(4, 1, 500),
            'minimum_stock' => $this->faker->optional()->randomFloat(4, 1, 10),
            'maximum_stock' => $this->faker->optional()->randomFloat(4, 20, 100),
            'reorder_point' => $this->faker->optional()->randomFloat(4, 5, 15),
            'reorder_quantity' => $this->faker->optional()->randomFloat(4, 5, 50),
            'lead_time_days' => $this->faker->optional()->numberBetween(1, 90),
            'is_active' => true,
            'created_by' => $user->id,
        ];
    }

    public function critical(): static
    {
        return $this->state(fn () => ['criticality' => SparePartCriticality::Critical->value]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
