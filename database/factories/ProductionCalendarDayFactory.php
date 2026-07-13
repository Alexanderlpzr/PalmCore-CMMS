<?php

namespace Database\Factories;

use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionCalendarDay>
 */
class ProductionCalendarDayFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'plant_id' => Plant::factory()->create(['tenant_id' => $tenant->id])->id,
            'calendar_date' => now()->toDateString(),
            'programmed_hours' => 20,
            'notes' => null,
        ];
    }

    /** A day the plant was never meant to run. */
    public function idle(): static
    {
        return $this->state(fn () => ['programmed_hours' => 0]);
    }
}
