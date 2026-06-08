<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    public function definition(): array
    {
        $tenant    = Tenant::factory()->create();
        $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
        $user      = User::factory()->create();

        return [
            'tenant_id'              => $tenant->id,
            'work_order_number'      => 'OT-'.date('Y').'-'.$equipment->code.'-'.str_pad($this->faker->unique()->numberBetween(1, 99999), 6, '0', STR_PAD_LEFT),
            'maintenance_request_id' => null,
            'equipment_id'           => $equipment->id,
            'plant_id'               => $equipment->plant_id,
            'area_id'                => $equipment->area_id,
            'work_order_type'        => $this->faker->randomElement(WorkOrderType::cases())->value,
            'status'                 => WorkOrderStatus::Draft->value,
            'priority'               => $this->faker->randomElement(WorkOrderPriority::cases())->value,
            'title'                  => $this->faker->sentence(6),
            'description'            => $this->faker->paragraph(3),
            'instructions'           => $this->faker->optional()->paragraph(2),
            'failure_cause'          => null,
            'work_performed'         => null,
            'root_cause'             => null,
            'rejection_reason'       => null,
            'equipment_stopped'      => false,
            'downtime_minutes'       => null,
            'planned_start_at'       => null,
            'planned_end_at'         => null,
            'planned_labor_hours'    => $this->faker->optional()->randomFloat(1, 1, 40),
            'actual_start_at'        => null,
            'actual_end_at'          => null,
            'actual_labor_hours'     => null,
            'estimated_cost'         => $this->faker->optional()->randomFloat(2, 50, 5000),
            'actual_cost_labor'      => null,
            'actual_cost_parts'      => null,
            'actual_cost_external'   => null,
            'actual_cost_total'      => null,
            'currency_code'          => 'COP',
            'created_by'             => $user->id,
            'assigned_supervisor'    => null,
            'completed_by'           => null,
            'verified_by'            => null,
            'started_at'             => null,
            'completed_at'           => null,
            'verified_at'            => null,
            'closed_at'              => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(fn () => [
            'status'          => WorkOrderStatus::Planned->value,
            'planned_start_at' => now()->addDay(),
            'planned_end_at'  => now()->addDays(2),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status'         => WorkOrderStatus::InProgress->value,
            'actual_start_at' => now()->subHours(2),
            'started_at'     => now()->subHours(2),
        ]);
    }

    public function completed(): static
    {
        return $this->afterMaking(function (WorkOrder $wo) {
            $wo->completed_by = User::factory()->create()->id;
        })->state(fn () => [
            'status'          => WorkOrderStatus::Completed->value,
            'actual_start_at' => now()->subHours(6),
            'actual_end_at'   => now(),
            'started_at'      => now()->subHours(6),
            'completed_at'    => now(),
            'actual_labor_hours' => 6.0,
            'work_performed'  => 'Se realizó el mantenimiento correctivo.',
        ]);
    }

    public function emergency(): static
    {
        return $this->state(fn () => [
            'work_order_type'   => WorkOrderType::Emergency->value,
            'priority'          => WorkOrderPriority::P1Critical->value,
            'status'            => WorkOrderStatus::InProgress->value,
            'equipment_stopped' => true,
        ]);
    }

    public function withEquipmentStopped(int $downtimeMinutes = 60): static
    {
        return $this->state(fn () => [
            'equipment_stopped' => true,
            'downtime_minutes'  => $downtimeMinutes,
        ]);
    }
}
