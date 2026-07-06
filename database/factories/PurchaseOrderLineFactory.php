<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SparePart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderLine>
 */
class PurchaseOrderLineFactory extends Factory
{
    public function definition(): array
    {
        $purchaseOrder = PurchaseOrder::factory()->create();
        $quantity = $this->faker->randomFloat(2, 1, 20);
        $unitCost = $this->faker->randomFloat(2, 1, 200);

        return [
            'tenant_id' => $purchaseOrder->tenant_id,
            'purchase_order_id' => $purchaseOrder->id,
            'spare_part_id' => SparePart::factory()->create(['tenant_id' => $purchaseOrder->tenant_id]),
            'quantity_ordered' => $quantity,
            'quantity_received' => 0,
            'unit_cost' => $unitCost,
            'line_total' => round($quantity * $unitCost, 2),
        ];
    }
}
