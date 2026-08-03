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
            // Las cifras de referencia de El Pajuil: HP 452 · HASEO 8 · HMTTO 14
            // · HOPER 10 · HPREN 420 · FP 6.000 t → 94,59 % · 13,51 t/h · 95,13 %.
            'programmed_hours' => 452,
            'lost_hours' => 32,
            'effective_hours' => 420,
            'maintenance_lost_hours' => 22,
            'cleaning_hours' => 8,
            'processed_tons' => 6000,
            'failure_count' => 5,
            'mtbf_hours' => 84.0,
            'mttr_hours' => 4,
            'calculated_at' => now(),
        ];
    }

    /** Un mes cuyas toneladas alguien corrigió a mano tras el cierre. */
    public function withCorrectedTonnage(float $tons): static
    {
        return $this->state(fn (): array => [
            'processed_tons' => $tons,
            'processed_tons_is_manual' => true,
        ]);
    }
}
