<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\WorkOrderPartStatus;
use App\Models\SparePart;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderPart>
 */
class WorkOrderPartFactory extends Factory
{
    public function definition(): array
    {
        $workOrder = WorkOrder::factory()->create();
        $quantity = $this->faker->randomFloat(2, 1, 20);
        $unitCost = $this->faker->optional(0.8)->randomFloat(2, 500, 50000);

        return [
            'tenant_id' => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'part_code' => $this->faker->optional()->bothify('REP-####'),
            'description' => $this->faker->words(3, true),
            'quantity' => $quantity,
            'unit' => $this->faker->randomElement(['pcs', 'kg', 'L', 'm', 'un']),
            'unit_cost' => $unitCost,
            'total_cost' => $unitCost !== null ? round($quantity * $unitCost, 2) : null,
            'status' => WorkOrderPartStatus::Requested->value,
            'reserved_quantity' => 0,
            'issued_quantity' => 0,
            'returned_quantity' => 0,
            'unit_cost_snapshot' => null,
        ];
    }

    /** Part linked to the inventory system (has spare_part + warehouse). */
    public function withInventoryLink(SparePart $sparePart, Warehouse $warehouse): static
    {
        return $this->state(fn (array $attributes) => [
            'spare_part_id' => $sparePart->id,
            'warehouse_id' => $warehouse->id,
            'unit_cost_snapshot' => $sparePart->unit_cost,
            'description' => $sparePart->name,
            'unit' => $sparePart->unit->value,
        ]);
    }

    public function reserved(float $quantity): static
    {
        return $this->state(fn () => [
            'status' => WorkOrderPartStatus::Reserved->value,
            'reserved_quantity' => $quantity,
        ]);
    }

    public function issued(float $quantity): static
    {
        return $this->state(fn () => [
            'status' => WorkOrderPartStatus::Issued->value,
            'issued_quantity' => $quantity,
            'reserved_quantity' => 0,
        ]);
    }
}
