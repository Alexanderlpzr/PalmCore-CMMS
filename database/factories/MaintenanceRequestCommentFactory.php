<?php

namespace Database\Factories;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRequestComment>
 */
class MaintenanceRequestCommentFactory extends Factory
{
    public function definition(): array
    {
        $request = MaintenanceRequest::factory()->create();

        return [
            'tenant_id'              => $request->tenant_id,
            'maintenance_request_id' => $request->id,
            'user_id'                => User::factory()->create()->id,
            'body'                   => $this->faker->paragraph(2),
            'is_internal'            => false,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn () => ['is_internal' => true]);
    }
}
