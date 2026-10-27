<?php

namespace Database\Factories;

use App\Models\R100Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<R100Lead>
 */
class R100LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'consented_at' => now(),
        ];
    }
}
