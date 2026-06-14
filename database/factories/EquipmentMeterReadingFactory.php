<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\MeterReadingUnit;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentMeterReading>
 */
class EquipmentMeterReadingFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'equipment_id' => $equipment->id,
            'reading_value' => $this->faker->randomFloat(1, 100, 10000),
            'reading_unit' => MeterReadingUnit::Hours->value,
            'recorded_at' => now(),
            'recorded_by' => $user->id,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
