<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\WorkedHoursPeriodType;
use App\Models\Equipment;
use App\Models\EquipmentWorkedHours;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentWorkedHours>
 */
class EquipmentWorkedHoursFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'equipment_id' => $equipment->id,
            'period_type' => WorkedHoursPeriodType::Diario->value,
            'log_date' => now()->toDateString(),
            'hours' => $this->faker->randomFloat(2, 1, 12),
            'recorded_by' => $user->id,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
