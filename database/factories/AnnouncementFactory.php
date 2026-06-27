<?php

namespace Database\Factories;

use App\Domain\Home\Enums\AnnouncementCategory;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'subtitle' => fake()->optional(0.5)->sentence(3),
            'body' => fake()->optional(0.8)->paragraphs(2, true),
            'category' => fake()->randomElement(AnnouncementCategory::cases())->value,
            'image_path' => null,
            'button_label' => fake()->optional(0.3)->words(2, true),
            'button_url' => fake()->optional(0.3)->url(),
            'is_active' => true,
            'is_pinned' => false,
            'sort_order' => fake()->numberBetween(0, 10),
            'published_at' => now(),
            'expires_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function pinned(): static
    {
        return $this->state(['is_pinned' => true]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function future(): static
    {
        return $this->state(['published_at' => now()->addDays(7)]);
    }
}
