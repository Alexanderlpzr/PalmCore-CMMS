<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\MaintenanceTimeFrequency;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenancePlan>
 */
class MaintenancePlanFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

        return [
            'tenant_id' => $tenant->id,
            'equipment_id' => $equipment->id,
            'plan_number' => 'PM-'.$equipment->code.'-MENSUAL',
            'name' => 'Mantenimiento '.$this->faker->word(),
            'description' => $this->faker->optional()->sentence(),
            'responsible_user_id' => null,
            'trigger_source' => MaintenanceTriggerSource::Calendar->value,
            'time_frequency' => MaintenanceTimeFrequency::Monthly->value,
            'meter_interval' => null,
            'cadence_mode' => 'fixed',
            'pause_when_equipment_inactive' => false,
            'grace_period_days' => null,
            'grace_meter_hours' => null,
            'estimated_duration_minutes' => $this->faker->optional()->numberBetween(30, 480),
            'is_active' => true,
            'last_generated_at' => null,
        ];
    }

    public function meterBased(int $interval = 500): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_source' => MaintenanceTriggerSource::Meter->value,
            'time_frequency' => null,
            'meter_interval' => $interval,
            'cadence_mode' => 'floating',
            'plan_number' => 'PM-TEST-'.$interval.'H',
        ]);
    }

    public function hybrid(string $timeFrequency = 'monthly', int $meterInterval = 500): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_source' => MaintenanceTriggerSource::Hybrid->value,
            'time_frequency' => $timeFrequency,
            'meter_interval' => $meterInterval,
            'cadence_mode' => 'fixed',
            'plan_number' => 'PM-TEST-HYBRID',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withGrace(int $days = 3, ?int $hours = null): static
    {
        return $this->state(fn () => [
            'grace_period_days' => $days,
            'grace_meter_hours' => $hours,
        ]);
    }
}
