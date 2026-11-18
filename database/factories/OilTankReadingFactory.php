<?php

namespace Database\Factories;

use App\Models\OilTank;
use App\Models\OilTankReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OilTankReading>
 */
class OilTankReadingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes): string => OilTank::withoutGlobalScopes()->find($attributes['oil_tank_id'])->tenant_id,
            'oil_tank_id' => OilTank::factory(),
            'reading_date' => now()->toDateString(),
            'level_tons' => $this->faker->randomFloat(2, 100, 900),
            'acidity_percent' => $this->faker->randomFloat(2, 2.5, 4.9),
        ];
    }
}
