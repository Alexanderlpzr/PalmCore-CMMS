<?php

namespace Database\Factories;

use App\Models\EnergyMeter;
use App\Models\EnergyMeterReading;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnergyMeterReading>
 */
class EnergyMeterReadingFactory extends Factory
{
    public function definition(): array
    {
        $meter = EnergyMeter::factory()->create();

        return [
            'tenant_id' => $meter->tenant_id,
            'energy_meter_id' => $meter->id,
            'reading_date' => now()->toDateString(),
            'reading_value' => 100000,
            'previous_value' => null,
            'delta' => 0,
            'accumulated_value' => 0,
            'is_reset' => false,
            'recorded_by' => User::factory()->create(['tenant_id' => $meter->tenant_id])->id,
            'notes' => null,
        ];
    }
}
