<?php

namespace Database\Factories;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Enums\Market;
use App\Models\Customer;
use App\Models\SalesDispatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Un cargue recién salido de la planta, en tránsito. Los estados siguientes llenan los
 * datos que los marcan: el descargue, la factura y el pago.
 *
 * @extends Factory<SalesDispatch>
 */
class SalesDispatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes): string => Customer::withoutGlobalScopes()->find($attributes['customer_id'])->tenant_id,
            'customer_id' => Customer::factory(),
            'product' => CommercialProduct::Cpo->value,
            'market' => Market::Nacional->value,
            'loaded_on' => now()->subDays($this->faker->numberBetween(1, 20))->toDateString(),
            'plate' => strtoupper($this->faker->bothify('???###')),
            'remission_number' => (string) $this->faker->unique()->numberBetween(8000, 99999),
            'departure_weight_tons' => $this->faker->randomFloat(2, 30, 35),
            'departure_acidity' => $this->faker->randomFloat(2, 3.5, 4.9),
            'freight_rate_per_ton' => 125000,
        ];
    }

    public function unloaded(?float $arrivalTons = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'unloaded_on' => now()->toDateString(),
            'arrival_weight_tons' => $arrivalTons ?? round((float) $attributes['departure_weight_tons'] - 0.06, 2),
            'arrival_acidity' => $attributes['departure_acidity'],
        ]);
    }

    public function invoiced(float $amount = 140_000_000): static
    {
        return $this->unloaded()->state([
            'invoice_number' => (string) $this->faker->unique()->numberBetween(900, 99999),
            'invoiced_on' => now()->toDateString(),
            'invoice_amount' => $amount,
        ]);
    }

    public function paid(): static
    {
        return $this->invoiced()->state(['paid_on' => now()->toDateString()]);
    }
}
