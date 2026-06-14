<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenancePlanAttachment>
 */
class MaintenancePlanAttachmentFactory extends Factory
{
    public function definition(): array
    {
        $plan = MaintenancePlan::factory()->create();
        $user = User::factory()->create();

        return [
            'tenant_id' => $plan->tenant_id,
            'maintenance_plan_id' => $plan->id,
            'attachment_label' => $this->faker->randomElement(['Manual SKF', 'Procedimiento WEG', 'Instructivo técnico', 'Plano eléctrico']),
            'file_path' => 'maintenance-plan-attachments/'.$this->faker->uuid().'.pdf',
            'file_name' => $this->faker->slug().'.pdf',
            'file_size' => $this->faker->numberBetween(50000, 5000000),
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->id,
        ];
    }
}
