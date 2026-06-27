<?php

namespace Database\Factories;

use App\Models\ComponentHistory;
use App\Models\EquipmentComponent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComponentHistory>
 */
class ComponentHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'equipment_component_id' => EquipmentComponent::factory(),
            'type' => $this->faker->randomElement(['installation', 'maintenance', 'inspection', 'failure', 'note']),
            'description' => $this->faker->sentence(),
            'worked_hours_at_event' => $this->faker->optional()->randomFloat(1, 0, 5000),
            'occurred_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
