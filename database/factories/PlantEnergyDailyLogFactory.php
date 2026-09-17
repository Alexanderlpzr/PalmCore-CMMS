<?php

namespace Database\Factories;

use App\Models\Plant;
use App\Models\PlantEnergyDailyLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlantEnergyDailyLog>
 */
class PlantEnergyDailyLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plant = Plant::factory()->create();

        return [
            'tenant_id' => $plant->tenant_id,
            'plant_id' => $plant->id,
            'log_date' => now()->toDateString(),
            'fuel_gallons' => 80.0,
            'energy_switch_count' => 1,
            'recorded_by' => null,
            'notes' => null,
        ];
    }

    public function forPlant(Plant $plant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $plant->tenant_id,
            'plant_id' => $plant->id,
        ]);
    }
}
