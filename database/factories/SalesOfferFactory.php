<?php

namespace Database\Factories;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Models\Customer;
use App\Models\SalesOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOffer>
 */
class SalesOfferFactory extends Factory
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
            'number' => $this->faker->unique()->numerify('48000#####'),
            // Las ofertas van por tractomulas de 34 tn.
            'contracted_tons' => 34 * $this->faker->numberBetween(1, 12),
            'signed_on' => now()->subDays($this->faker->numberBetween(5, 60))->toDateString(),
        ];
    }
}
