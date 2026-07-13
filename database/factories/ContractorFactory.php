<?php

namespace Database\Factories;

use App\Models\Contractor;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contractor>
 */
class ContractorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->unique()->company(),
            'tax_id' => $this->faker->numerify('#########-#'),
            'specialty' => $this->faker->randomElement(['Mecánico', 'Eléctrico', 'Montajes', 'Instrumentación']),
            'contact_name' => $this->faker->name(),
            'contact_phone' => $this->faker->numerify('3##-###-####'),
            'contact_email' => $this->faker->safeEmail(),
            'hourly_rate' => $this->faker->numberBetween(50_000, 200_000),
            'currency_code' => 'COP',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
