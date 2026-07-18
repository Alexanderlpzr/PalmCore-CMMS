<?php

namespace Database\Factories;

use App\Models\MaintenanceBudget;
use App\Models\Plant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceBudget>
 */
class MaintenanceBudgetFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'plant_id' => Plant::factory()->create(['tenant_id' => $tenant->id])->id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'amount' => 50_000_000,
            'notes' => null,
            'created_by' => null,
        ];
    }
}
