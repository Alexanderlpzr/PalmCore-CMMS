<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\EquipmentSparePart;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentSparePart>
 */
class EquipmentSparePartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'equipment_id' => Equipment::factory(),
            'name' => fake()->words(3, true),
            'part_number' => fake()->optional(0.5)->bothify('REF-####'),
            'unit_cost' => fake()->optional(0.7)->randomFloat(2, 5000, 2000000),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}
