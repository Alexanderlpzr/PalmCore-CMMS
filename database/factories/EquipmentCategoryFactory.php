<?php

namespace Database\Factories;

use App\Models\EquipmentCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentCategory>
 */
class EquipmentCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'   => Tenant::factory(),
            'parent_id'   => null,
            'code'        => strtoupper($this->faker->unique()->lexify('CAT-???')),
            'name'        => $this->faker->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'icon'        => null,
            'sort_order'  => $this->faker->numberBetween(0, 100),
            'is_active'   => true,
        ];
    }

    public function withParent(EquipmentCategory $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
