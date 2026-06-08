<?php

namespace Database\Factories;

use App\Domain\Assets\Enums\IssueSeverity;
use App\Models\Equipment;
use App\Models\EquipmentIssueReport;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentIssueReport>
 */
class EquipmentIssueReportFactory extends Factory
{
    public function definition(): array
    {
        $tenant    = Tenant::factory()->create();
        $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

        return [
            'equipment_id'     => $equipment->id,
            'tenant_id'        => $tenant->id,
            'qr_code_id'       => null,
            'description'      => $this->faker->paragraph(2),
            'severity'         => $this->faker->randomElement(IssueSeverity::cases())->value,
            'reporter_name'    => $this->faker->optional()->name(),
            'reporter_phone'   => $this->faker->optional()->numerify('3## ### ####'),
            'reporter_user_id' => null,
            'status'           => 'open',
            'acknowledged_at'  => null,
            'acknowledged_by'  => null,
            'admin_notes'      => null,
        ];
    }

    public function critical(): static
    {
        return $this->state(fn () => ['severity' => IssueSeverity::Critical->value]);
    }

    public function acknowledged(): static
    {
        return $this->state(fn () => [
            'status'          => 'acknowledged',
            'acknowledged_at' => now(),
        ]);
    }
}
