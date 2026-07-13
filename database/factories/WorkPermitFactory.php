<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\WorkPermitStatus;
use App\Domain\Maintenance\Enums\WorkPermitType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkPermit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkPermit>
 */
class WorkPermitFactory extends Factory
{
    public function definition(): array
    {
        $workOrder = WorkOrder::factory()->create();

        return [
            'tenant_id' => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'permit_number' => 'PT-'.now()->year.'-'.$this->faker->unique()->numerify('#####'),
            'permit_type' => WorkPermitType::HotWork->value,
            'status' => WorkPermitStatus::Issued->value,
            'hazards' => 'Chispa sobre fibra y cuesco; atmósfera inflamable.',
            'controls' => 'Extintor en sitio, vigía de fuego, retiro de material combustible en 10 m.',
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addHours(8),
            'issued_by' => User::factory(),
            'issued_at' => now()->subHour(),
        ];
    }

    /** Firmado por el ejecutante: es el único estado que autoriza el trabajo. */
    public function accepted(?User $acceptedBy = null): static
    {
        return $this->state(fn (): array => [
            'status' => WorkPermitStatus::Accepted->value,
            'accepted_by' => $acceptedBy?->id ?? User::factory(),
            'accepted_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'valid_from' => now()->subDays(2),
            'valid_until' => now()->subDay(),
        ]);
    }

    public function confinedSpace(): static
    {
        return $this->state(fn (): array => [
            'permit_type' => WorkPermitType::ConfinedSpace->value,
            'hazards' => 'Atmósfera deficiente de oxígeno dentro del digestor.',
            'controls' => 'Medición de gases, ventilación forzada, vigía externo permanente.',
            'isolation_points' => ['Breaker CCM-04 bloqueado con candado rojo', 'Válvula de vapor V-12 cerrada y etiquetada'],
        ]);
    }
}
