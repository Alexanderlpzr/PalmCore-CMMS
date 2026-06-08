<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name'                  => $name,
            'slug'                  => str($name)->slug('-')->limit(100)->toString(),
            'tax_id'                => $this->faker->optional()->numerify('###.###.###-#'),
            'contact_email'         => $this->faker->optional()->companyEmail(),
            'contact_phone'         => $this->faker->optional()->numerify('+57 3## ### ####'),
            'country_code'          => 'COL',
            'timezone'              => 'America/Bogota',
            'locale'                => 'es_CO',
            'subscription_plan'     => 'starter',
            'subscription_expires_at' => null,
            'is_active'             => true,
            'logo_path'             => null,
            'settings'              => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function enterprise(): static
    {
        return $this->state(fn () => [
            'subscription_plan'       => 'enterprise',
            'subscription_expires_at' => now()->addYear(),
        ]);
    }
}
