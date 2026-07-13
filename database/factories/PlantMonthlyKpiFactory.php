<?php

namespace Database\Factories;

use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlantMonthlyKpi>
 */
class PlantMonthlyKpiFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'plant_id' => Plant::factory()->create(['tenant_id' => $tenant->id])->id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'programmed_hours' => 452,
            'lost_hours' => 38.6,
            'effective_hours' => 413.4,
            'maintenance_lost_hours' => 20,
            'failure_count' => 5,
            'mtbf_hours' => 82.68,
            'mttr_hours' => 4,
            'calculated_at' => now(),
        ];
    }
}
