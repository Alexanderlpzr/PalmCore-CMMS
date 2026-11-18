<?php

namespace Database\Factories;

use App\Models\OilTank;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OilTank>
 */
class OilTankFactory extends Factory
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
            'name' => 'Tanque '.$this->faker->unique()->numberBetween(1, 99),
            'capacity_tons' => 1000,
            'is_active' => true,
        ];
    }
}
