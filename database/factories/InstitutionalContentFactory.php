<?php

namespace Database\Factories;

use App\Domain\Home\Enums\InstitutionalContentType;
use App\Models\InstitutionalContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstitutionalContent>
 */
class InstitutionalContentFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(InstitutionalContentType::cases());
        $start = fake()->optional(0.4)->dateTimeBetween('-7 days', '+7 days');
        $end = $start ? fake()->optional(0.6)->dateTimeBetween('+7 days', '+30 days') : null;

        return [
            'title' => fake()->sentence(5),
            'subtitle' => fake()->optional(0.6)->sentence(4),
            'description' => fake()->optional(0.7)->paragraphs(2, true),
            'image_path' => null,
            'button_text' => fake()->optional(0.4)->words(3, true),
            'button_url' => fake()->optional(0.4)->url(),
            'type' => $type->value,
            'display_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
            'is_global' => true,
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

    public function forTenant(): static
    {
        return $this->state(['is_global' => false]);
    }
}
