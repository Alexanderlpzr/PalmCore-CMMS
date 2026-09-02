<?php

namespace Database\Factories;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Models\PayrollParameterVersion;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollParameterVersion>
 */
class PayrollParameterVersionFactory extends Factory
{
    protected $model = PayrollParameterVersion::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => PayrollParameter::SurchargeNight->value,
            'value' => 0.35,
            'effective_from' => now()->startOfYear()->toDateString(),
            'effective_to' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }

    /** No se llama `for`: ese nombre es de `Factory` y sirve para relaciones. */
    public function parameter(PayrollParameter $parameter, float $value): static
    {
        return $this->state(fn (): array => [
            'key' => $parameter->value,
            'value' => $value,
        ]);
    }

    public function closed(string $from, string $to): static
    {
        return $this->state(fn (): array => [
            'effective_from' => $from,
            'effective_to' => $to,
        ]);
    }
}
