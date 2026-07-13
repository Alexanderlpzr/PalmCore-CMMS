<?php

namespace Database\Factories;

use App\Domain\Assets\Enums\EquipmentDowntimeCauseType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Maintenance\Enums\FailureMode;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentDowntimeEvent>
 */
class EquipmentDowntimeEventFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
        $startedAt = now()->subHours($this->faker->numberBetween(2, 72));
        $endedAt = $startedAt->copy()->addMinutes($this->faker->numberBetween(30, 480));

        return [
            'tenant_id' => $tenant->id,
            'plant_id' => $equipment->plant_id,
            'equipment_id' => $equipment->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_minutes' => (int) $startedAt->diffInMinutes($endedAt),
            'cause_type' => $this->faker->randomElement(EquipmentDowntimeCauseType::cases())->value,
            'stoppage_category' => $this->faker->randomElement(StoppageCategory::cases())->value,
            'stoppage_cause' => $this->faker->optional()->sentence(3),
            'was_planned' => false,
            'affects_production' => true,
            'source' => 'manual',
            'failure_mode' => $this->faker->optional()->randomElement(array_column(FailureMode::cases(), 'value')),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /** A paro de planta: the whole line is down and no single equipment is to blame. */
    public function plantWide(StoppageCategory $category = StoppageCategory::RawMaterial): static
    {
        return $this->state(fn (array $attributes) => [
            'equipment_id' => null,
            'stoppage_category' => $category->value,
            'cause_type' => EquipmentDowntimeCauseType::External->value,
        ]);
    }

    public function ongoing(): static
    {
        return $this->state(fn () => [
            'ended_at' => null,
            'duration_minutes' => null,
        ]);
    }

    public function planned(): static
    {
        return $this->state(fn () => [
            'cause_type' => EquipmentDowntimeCauseType::Preventive->value,
            'was_planned' => true,
        ]);
    }
}
