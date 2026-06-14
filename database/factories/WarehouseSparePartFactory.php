<?php

namespace Database\Factories;

use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseSparePart>
 */
class WarehouseSparePartFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
        $sparePart = SparePart::factory()->create(['tenant_id' => $tenant->id]);

        return [
            'tenant_id' => $tenant->id,
            'warehouse_id' => $warehouse->id,
            'spare_part_id' => $sparePart->id,
            'current_stock' => $this->faker->randomFloat(4, 0, 50),
            'reserved_stock' => 0,
            'average_unit_cost' => $sparePart->unit_cost,
            'bin_location' => $this->faker->optional()->bothify('?-##-#'),
        ];
    }

    public function withStock(float $stock): static
    {
        return $this->state(fn () => ['current_stock' => $stock]);
    }

    public function empty(): static
    {
        return $this->state(fn () => ['current_stock' => 0, 'reserved_stock' => 0]);
    }
}
