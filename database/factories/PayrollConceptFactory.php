<?php

namespace Database\Factories;

use App\Domain\HumanResources\Enums\PayrollConceptType;
use App\Models\PayrollConcept;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollConcept>
 */
class PayrollConceptFactory extends Factory
{
    protected $model = PayrollConcept::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => strtoupper(fake()->unique()->bothify('CPT-###')),
            'name' => 'Concepto de prueba',
            'type' => PayrollConceptType::Devengado,
            'counts_ibc_health' => true,
            'counts_ibc_pension' => true,
            'counts_severance_base' => true,
            'counts_vacation_base' => true,
            'is_active' => true,
            'sort_order' => 0,
            'notes' => null,
        ];
    }

    /**
     * El auxilio de transporte: no entra al IBC pero sí a la base de prima. Es el caso
     * que más se equivoca y por eso vive como estado con nombre.
     */
    public function transportAllowance(): static
    {
        return $this->state(fn (): array => [
            'code' => 'AUX_TRANSPORTE',
            'name' => 'Auxilio de transporte',
            'counts_ibc_health' => false,
            'counts_ibc_pension' => false,
            'counts_severance_base' => true,
            'counts_vacation_base' => false,
        ]);
    }

    public function deduction(): static
    {
        return $this->state(fn (): array => [
            'type' => PayrollConceptType::Deduccion,
            'counts_ibc_health' => false,
            'counts_ibc_pension' => false,
            'counts_severance_base' => false,
            'counts_vacation_base' => false,
        ]);
    }
}
