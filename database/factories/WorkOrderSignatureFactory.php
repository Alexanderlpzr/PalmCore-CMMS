<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\WorkOrderSignatureType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderSignature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderSignature>
 */
class WorkOrderSignatureFactory extends Factory
{
    public function definition(): array
    {
        $workOrder = WorkOrder::factory()->create();

        return [
            'tenant_id'      => $workOrder->tenant_id,
            'work_order_id'  => $workOrder->id,
            'user_id'        => User::factory()->create()->id,
            'signature_type' => WorkOrderSignatureType::TechnicianCompletion->value,
            'signed_at'      => now(),
            'notes'          => $this->faker->optional()->sentence(5),
        ];
    }

    public function supervisor(): static
    {
        return $this->state(fn () => [
            'signature_type' => WorkOrderSignatureType::SupervisorVerification->value,
        ]);
    }
}
