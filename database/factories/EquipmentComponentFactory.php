<?php

namespace Database\Factories;

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentComponent>
 */
class EquipmentComponentFactory extends Factory
{
    private static int $nextCode = 0;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'equipment_id' => Equipment::factory(),
            'parent_id' => null,
            // equipment_components carries unique(equipment_id, code). A random
            // COMP-### has only 1000 values, so two components on one equipment
            // collide now and then and the suite fails for no reason. Sequential
            // codes cannot repeat within a run.
            'code' => sprintf('COMP-%04d', ++static::$nextCode),
            'name' => fake()->words(3, true),
            'manufacturer' => fake()->optional(0.6)->company(),
            'model' => fake()->optional(0.5)->bothify('MDL-???##'),
            'serial_number' => fake()->optional(0.4)->bothify('SN-########'),
            'criticality' => fake()->randomElement(EquipmentCriticality::cases())->value,
            'part_number' => null,
            'status' => 'active',
            'worked_hours' => null,
            'useful_life_hours' => fake()->optional(0.5)->numberBetween(500, 50000),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function critical(): static
    {
        return $this->state(['criticality' => EquipmentCriticality::Critical->value]);
    }

    public function forEquipment(Equipment $equipment): static
    {
        return $this->state([
            'tenant_id' => $equipment->tenant_id,
            'equipment_id' => $equipment->id,
        ]);
    }
}
