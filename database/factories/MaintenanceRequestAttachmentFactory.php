<?php

namespace Database\Factories;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRequestAttachment>
 */
class MaintenanceRequestAttachmentFactory extends Factory
{
    public function definition(): array
    {
        $request = MaintenanceRequest::factory()->create();

        return [
            'tenant_id'              => $request->tenant_id,
            'maintenance_request_id' => $request->id,
            'file_path'              => 'maintenance-attachments/'.$request->tenant_id.'/'.$this->faker->uuid().'.jpg',
            'file_name'              => $this->faker->word().'.jpg',
            'file_size'              => $this->faker->numberBetween(10000, 5000000),
            'mime_type'              => 'image/jpeg',
            'caption'                => $this->faker->optional()->sentence(5),
            'uploaded_by'            => User::factory()->create()->id,
        ];
    }

    public function pdf(): static
    {
        return $this->state(fn () => [
            'file_name' => $this->faker->word().'.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }
}
