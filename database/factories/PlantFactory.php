<?php

namespace Database\Factories;

use App\Models\Plant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plant>
 */
class PlantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'      => Tenant::factory(),
            'code'           => strtoupper($this->faker->unique()->lexify('PLT-??')),
            'name'           => $this->faker->words(3, true).' Plant',
            'address'        => $this->faker->optional()->address(),
            'latitude'       => $this->faker->optional()->latitude(-5, 15),
            'longitude'      => $this->faker->optional()->longitude(-80, -65),
            'city'           => $this->faker->optional()->city(),
            'state_province' => $this->faker->optional()->state(),
            'country_code'   => 'COL',
            'timezone'       => null,
            'is_active'      => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
