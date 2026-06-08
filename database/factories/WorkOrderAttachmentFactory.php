<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\WorkOrderAttachmentType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderAttachment>
 */
class WorkOrderAttachmentFactory extends Factory
{
    public function definition(): array
    {
        $workOrder = WorkOrder::factory()->create();

        return [
            'tenant_id'       => $workOrder->tenant_id,
            'work_order_id'   => $workOrder->id,
            'attachment_type' => $this->faker->randomElement(WorkOrderAttachmentType::cases())->value,
            'file_path'       => 'work-order-attachments/'.$workOrder->tenant_id.'/'.$workOrder->id.'/'.$this->faker->uuid().'.jpg',
            'file_name'       => $this->faker->word().'.jpg',
            'file_size'       => $this->faker->numberBetween(10000, 5000000),
            'mime_type'       => 'image/jpeg',
            'caption'         => $this->faker->optional()->sentence(4),
            'uploaded_by'     => User::factory()->create()->id,
        ];
    }

    public function beforePhoto(): static
    {
        return $this->state(fn () => ['attachment_type' => WorkOrderAttachmentType::BeforePhoto->value]);
    }

    public function afterPhoto(): static
    {
        return $this->state(fn () => ['attachment_type' => WorkOrderAttachmentType::AfterPhoto->value]);
    }
}
