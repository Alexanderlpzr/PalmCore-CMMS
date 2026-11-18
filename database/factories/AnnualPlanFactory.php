<?php

namespace Database\Factories;

use App\Domain\Commercial\Enums\PlanVersion;
use App\Models\AnnualPlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Un plan original sin aprobar. Lo normal es crearlo con AnnualPlanService.
 *
 * @extends Factory<AnnualPlan>
 */
class AnnualPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'year' => (int) now()->format('Y'),
            'version' => PlanVersion::Original->value,
        ];
    }
}
