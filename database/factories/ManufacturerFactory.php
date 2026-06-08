<?php

namespace Database\Factories;

use App\Models\Manufacturer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Manufacturer>
 */
class ManufacturerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'     => Tenant::factory(),
            'code'          => strtoupper($this->faker->unique()->lexify('MFR-???')),
            'name'          => $this->faker->company(),
            'country_code'  => $this->faker->optional()->countryCode(),
            'website'       => $this->faker->optional()->url(),
            'contact_email' => $this->faker->optional()->safeEmail(),
            'contact_phone' => $this->faker->optional()->phoneNumber(),
            'notes'         => $this->faker->optional()->sentence(),
            'is_active'     => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
