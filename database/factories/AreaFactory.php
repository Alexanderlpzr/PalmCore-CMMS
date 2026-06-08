<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Plant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        $plant = Plant::factory()->create();

        return [
            'tenant_id'   => $plant->tenant_id,
            'plant_id'    => $plant->id,
            'code'        => strtoupper($this->faker->unique()->lexify('AREA-??')),
            'name'        => $this->faker->words(2, true).' Area',
            'description' => $this->faker->optional()->sentence(),
            'sort_order'  => $this->faker->numberBetween(1, 100) * 10,
            'is_active'   => true,
        ];
    }

    public function forPlant(Plant $plant): static
    {
        return $this->state(fn () => [
            'tenant_id' => $plant->tenant_id,
            'plant_id'  => $plant->id,
        ]);
    }
}
