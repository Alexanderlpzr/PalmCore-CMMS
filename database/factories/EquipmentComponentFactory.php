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
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'equipment_id' => Equipment::factory(),
            'parent_id' => null,
            'code' => strtoupper(fake()->bothify('COMP-###')),
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
