<?php

namespace Database\Factories;

use App\Domain\Commercial\Enums\Market;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
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
            'name' => $this->faker->unique()->company(),
            'market' => Market::Nacional->value,
            'tax_id' => $this->faker->optional()->numerify('#########-#'),
            'is_active' => true,
        ];
    }

    public function export(): static
    {
        return $this->state(['market' => Market::Exportacion->value]);
    }
}
