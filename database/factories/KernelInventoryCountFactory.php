<?php

namespace Database\Factories;

use App\Models\KernelInventoryCount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KernelInventoryCount>
 */
class KernelInventoryCountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'counted_on' => now()->toDateString(),
            'pko_tons' => $this->faker->randomFloat(2, 10, 80),
            'cake_tons' => $this->faker->randomFloat(2, 10, 80),
        ];
    }
}
