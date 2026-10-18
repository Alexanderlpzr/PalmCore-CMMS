<?php

namespace Database\Factories;

use App\Domain\Inventory\Enums\InventoryCountScope;
use App\Domain\Inventory\Enums\InventoryCountStatus;
use App\Models\InventoryCount;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCount>
 */
class InventoryCountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'warehouse_id' => Warehouse::factory()->create(['tenant_id' => $tenant->id]),
            'count_number' => 'CNT-'.date('Y').'-'.$this->faker->unique()->numerify('####'),
            'status' => InventoryCountStatus::Open->value,
            'scope' => InventoryCountScope::All->value,
            'created_by' => User::factory(),
        ];
    }
}
