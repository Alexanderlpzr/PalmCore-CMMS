<?php

namespace Database\Factories;

use App\Models\R100DailyUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<R100DailyUsage>
 */
class R100DailyUsageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $visits = fake()->numberBetween(5, 80);

        return [
            'date' => fake()->unique()->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'visits' => $visits,
            'calculations' => fake()->numberBetween(0, $visits),
        ];
    }
}
