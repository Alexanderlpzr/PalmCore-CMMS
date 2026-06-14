<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\EquipmentKpi;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentKpi>
 */
class EquipmentKpiFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
        $periodEnd = now()->toDateString();
        $periodStart = now()->subMonths(12)->toDateString();

        return [
            'tenant_id' => $tenant->id,
            'equipment_id' => $equipment->id,
            'period_months' => 12,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'mtbf_hours' => null,
            'mttr_hours' => null,
            'availability_percentage' => null,
            'unplanned_availability_percentage' => null,
            'failure_count' => 0,
            'downtime_hours' => 0,
            'last_failure_at' => null,
            'last_calculated_at' => now(),
            'is_stale' => false,
        ];
    }

    public function stale(): static
    {
        return $this->state(fn () => ['is_stale' => true]);
    }

    public function withFailures(int $count, float $mtbf, float $mttr): static
    {
        return $this->state(fn () => [
            'failure_count' => $count,
            'mtbf_hours' => $mtbf,
            'mttr_hours' => $mttr,
            'downtime_hours' => round($mttr * $count, 2),
            'availability_percentage' => round(100 - ($mttr * $count / (8760 / 12 * 12)) * 100, 2),
            'unplanned_availability_percentage' => round(100 - ($mttr * $count / (8760 / 12 * 12)) * 100, 2),
            'last_failure_at' => now()->subDays(rand(1, 30)),
        ]);
    }
}
