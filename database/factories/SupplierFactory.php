<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id'     => Tenant::factory(),
            'code'          => strtoupper($this->faker->unique()->lexify('SUP-???')),
            'name'          => $this->faker->company(),
            'tax_id'        => $this->faker->optional()->numerify('##########'),
            'contact_name'  => $this->faker->optional()->name(),
            'contact_email' => $this->faker->optional()->safeEmail(),
            'contact_phone' => $this->faker->optional()->phoneNumber(),
            'address'       => $this->faker->optional()->streetAddress(),
            'city'          => $this->faker->optional()->city(),
            'country_code'  => $this->faker->optional()->countryCode(),
            'notes'         => $this->faker->optional()->sentence(),
            'is_active'     => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
