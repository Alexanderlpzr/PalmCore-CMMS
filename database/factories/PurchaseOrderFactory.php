<?php

namespace Database\Factories;

use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'po_number' => 'OC-'.date('Y').'-'.$this->faker->unique()->numerify('######'),
            'supplier_id' => Supplier::factory()->create(['tenant_id' => $tenant->id]),
            'warehouse_id' => Warehouse::factory()->create(['tenant_id' => $tenant->id]),
            'status' => PurchaseOrderStatus::Draft->value,
            'currency_code' => 'COP',
            'total' => 0,
            'expected_at' => now()->addWeek(),
            'notes' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::Sent->value,
            'ordered_at' => now(),
        ]);
    }
}
