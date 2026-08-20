<?php

namespace Database\Factories;

use App\Domain\Energy\Enums\EnergySource;
use App\Models\EnergyMeter;
use App\Models\Plant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnergyMeter>
 */
class EnergyMeterFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'plant_id' => Plant::factory()->create(['tenant_id' => $tenant->id])->id,
            'code' => 'MED-'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Contador de prueba',
            'source' => EnergySource::Grid,
            'equipment_id' => null,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }

    public function grid(): static
    {
        return $this->state(fn (): array => [
            'source' => EnergySource::Grid,
            'name' => 'Red pública',
            'sort_order' => 1,
        ]);
    }

    public function genset(): static
    {
        return $this->state(fn (): array => [
            'source' => EnergySource::Genset,
            'name' => 'Planta eléctrica',
            'sort_order' => 2,
        ]);
    }

    public function turbine(): static
    {
        return $this->state(fn (): array => [
            'source' => EnergySource::Turbine,
            'name' => 'Turbina',
            'sort_order' => 3,
        ]);
    }
}
