<?php

namespace Database\Factories;

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Enums\EquipmentPriority;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Manufacturer;
use App\Models\Plant;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $plant  = Plant::factory()->create(['tenant_id' => $tenant->id]);

        return [
            'tenant_id'           => $tenant->id,
            'plant_id'            => $plant->id,
            'area_id'             => Area::factory()->create(['tenant_id' => $tenant->id, 'plant_id' => $plant->id])->id,
            'category_id'         => null,
            'manufacturer_id'     => null,
            'supplier_id'         => null,
            'parent_equipment_id' => null,
            'code'                => strtoupper($this->faker->unique()->lexify('EQ-?????')),
            'name'                => $this->faker->words(3, true),
            'model'               => $this->faker->optional()->bothify('Model-##??'),
            'serial_number'       => $this->faker->boolean(50) ? $this->faker->bothify('SN-########') : null,
            'asset_tag'           => $this->faker->optional()->bothify('TAG-#####'),
            'status'              => EquipmentStatus::Active,
            'criticality'         => $this->faker->randomElement(EquipmentCriticality::cases()),
            'priority'            => $this->faker->randomElement(EquipmentPriority::cases()),
            'purchase_date'       => $this->faker->optional()->dateTimeBetween('-5 years', '-1 year'),
            'installation_date'   => $this->faker->optional()->dateTimeBetween('-4 years', 'now'),
            'commissioning_date'  => $this->faker->optional()->dateTimeBetween('-4 years', 'now'),
            'warranty_expiry_date' => $this->faker->optional()->dateTimeBetween('now', '+3 years'),
            'useful_life_years'   => $this->faker->optional()->randomFloat(2, 2, 25),
            'purchase_price'      => $this->faker->optional()->randomFloat(2, 1000, 500000),
            'replacement_cost'    => $this->faker->optional()->randomFloat(2, 1000, 600000),
            'currency_code'       => 'USD',
            'location_notes'      => $this->faker->optional()->sentence(),
            'technical_specs'     => null,
            'notes'               => $this->faker->optional()->paragraph(),
            'is_active'           => true,
        ];
    }

    public function critical(): static
    {
        return $this->state(fn () => [
            'criticality' => EquipmentCriticality::Critical,
            'priority'    => EquipmentPriority::P1,
        ]);
    }

    public function underMaintenance(): static
    {
        return $this->state(fn () => [
            'status' => EquipmentStatus::UnderMaintenance,
        ]);
    }

    public function retired(): static
    {
        return $this->state(fn () => [
            'status'         => EquipmentStatus::Retired,
            'retired_at'     => now(),
            'retired_reason' => 'Fin de vida útil',
            'is_active'      => false,
        ]);
    }

    public function withCategory(EquipmentCategory $category): static
    {
        return $this->state(fn () => ['category_id' => $category->id]);
    }

    public function withManufacturer(Manufacturer $manufacturer): static
    {
        return $this->state(fn () => ['manufacturer_id' => $manufacturer->id]);
    }

    public function withSupplier(Supplier $supplier): static
    {
        return $this->state(fn () => ['supplier_id' => $supplier->id]);
    }
}
