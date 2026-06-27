<?php

namespace Database\Factories;

use App\Models\CarouselSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarouselSlide>
 */
class CarouselSlideFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->optional(0.4)->dateTimeBetween('-7 days', '+7 days');
        $end = $start ? fake()->optional(0.6)->dateTimeBetween('+7 days', '+30 days') : null;

        return [
            'title' => fake()->sentence(4),
            'subtitle' => fake()->optional(0.6)->sentence(3),
            'description' => fake()->optional(0.5)->paragraph(),
            'image_path' => null,
            'button_label' => fake()->optional(0.4)->words(2, true),
            'button_url' => fake()->optional(0.4)->url(),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
            'starts_at' => $start,
            'ends_at' => $end,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state([
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
        ]);
    }
}
