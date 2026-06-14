<?php

namespace Database\Factories;

use App\Domain\Inventory\Enums\InventoryTransactionType;
use App\Models\InventoryTransaction;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryTransaction>
 */
class InventoryTransactionFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
        $sparePart = SparePart::factory()->create(['tenant_id' => $tenant->id]);
        $wsp = WarehouseSparePart::factory()->create([
            'tenant_id' => $tenant->id,
            'warehouse_id' => $warehouse->id,
            'spare_part_id' => $sparePart->id,
        ]);
        $user = User::factory()->create();
        $quantity = $this->faker->randomFloat(4, 1, 20);
        $unitCost = $this->faker->randomFloat(4, 1, 200);

        return [
            'tenant_id' => $tenant->id,
            'warehouse_id' => $warehouse->id,
            'spare_part_id' => $sparePart->id,
            'warehouse_spare_part_id' => $wsp->id,
            'transaction_number' => 'MVT-'.date('Y').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'type' => InventoryTransactionType::Entry->value,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 4),
            'previous_stock' => 0,
            'new_stock' => $quantity,
            'spare_part_code_snapshot' => $sparePart->code,
            'spare_part_name_snapshot' => $sparePart->name,
            'performed_by' => $user->id,
            'performed_at' => now(),
        ];
    }

    public function consumption(): static
    {
        return $this->state(fn (array $a) => [
            'type' => InventoryTransactionType::Consumption->value,
            'quantity' => -abs((float) $a['quantity']),
        ]);
    }
}
