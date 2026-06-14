<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'code' => strtoupper($this->faker->unique()->bothify('WH-##')),
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'location' => $this->faker->optional()->address(),
            'is_active' => true,
            'created_by' => $user->id,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
