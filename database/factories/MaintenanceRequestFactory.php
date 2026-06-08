<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\MaintenanceRequestPriority;
use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Enums\MaintenanceRequestType;
use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRequest>
 */
class MaintenanceRequestFactory extends Factory
{
    public function definition(): array
    {
        $tenant    = Tenant::factory()->create();
        $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
        $user      = User::factory()->create();

        return [
            'tenant_id'          => $tenant->id,
            'request_number'     => 'MR-'.date('Y').'-'.str_pad($this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'issue_report_id'    => null,
            'equipment_id'       => $equipment->id,
            'request_type'       => $this->faker->randomElement(MaintenanceRequestType::cases())->value,
            'priority'           => $this->faker->randomElement(MaintenanceRequestPriority::cases())->value,
            'status'             => MaintenanceRequestStatus::Draft->value,
            'title'              => $this->faker->sentence(6),
            'description'        => $this->faker->paragraph(3),
            'requested_due_date' => $this->faker->optional()->dateTimeBetween('+1 week', '+3 months')?->format('Y-m-d'),
            'rejection_reason'   => null,
            'created_by'         => $user->id,
            'assigned_reviewer'  => null,
            'approved_by'        => null,
            'rejected_by'        => null,
            'submitted_at'       => null,
            'reviewed_at'        => null,
            'approved_at'        => null,
            'rejected_at'        => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status'       => MaintenanceRequestStatus::Submitted->value,
            'submitted_at' => now(),
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn () => [
            'status'       => MaintenanceRequestStatus::UnderReview->value,
            'submitted_at' => now()->subHours(2),
            'reviewed_at'  => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->afterMaking(function (MaintenanceRequest $mr) {
            $reviewer = User::factory()->create();
            $mr->assigned_reviewer = $reviewer->id;
            $mr->approved_by       = $reviewer->id;
        })->state(fn () => [
            'status'      => MaintenanceRequestStatus::Approved->value,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->afterMaking(function (MaintenanceRequest $mr) {
            $mr->rejected_by = User::factory()->create()->id;
        })->state(fn () => [
            'status'           => MaintenanceRequestStatus::Rejected->value,
            'rejected_at'      => now(),
            'rejection_reason' => 'No hay presupuesto disponible para este período.',
        ]);
    }

    public function emergency(): static
    {
        return $this->state(fn () => [
            'request_type' => MaintenanceRequestType::Emergency->value,
            'priority'     => MaintenanceRequestPriority::P1Critical->value,
            'status'       => MaintenanceRequestStatus::UnderReview->value,
        ]);
    }
}
