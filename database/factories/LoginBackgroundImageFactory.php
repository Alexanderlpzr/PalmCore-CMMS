<?php

namespace Database\Factories;

use App\Models\LoginBackgroundImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginBackgroundImage>
 */
class LoginBackgroundImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'image_path' => 'login/'.fake()->uuid().'.jpg',
            'caption' => fake()->optional(0.6)->sentence(4),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
