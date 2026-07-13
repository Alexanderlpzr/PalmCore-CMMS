<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    private static int $nextSortOrder = 0;

    public function definition(): array
    {
        $plant = Plant::factory()->create();

        return [
            'tenant_id' => $plant->tenant_id,
            'plant_id' => $plant->id,
            'code' => strtoupper($this->faker->unique()->lexify('AREA-??')),
            'name' => $this->faker->words(2, true).' Area',
            'description' => $this->faker->optional()->sentence(),
            // areas carries unique(plant_id, sort_order), so a random draw collides
            // sooner or later and the suite fails for no reason. Sequential is the
            // only honest way to satisfy that constraint from a factory.
            'sort_order' => static::$nextSortOrder += 10,
            'is_active' => true,
        ];
    }

    public function forPlant(Plant $plant): static
    {
        return $this->state(fn () => [
            'tenant_id' => $plant->tenant_id,
            'plant_id' => $plant->id,
        ]);
    }
}
