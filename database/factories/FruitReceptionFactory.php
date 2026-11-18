<?php

namespace Database\Factories;

use App\Models\FruitReception;
use App\Models\FruitSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FruitReception>
 */
class FruitReceptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes): string => FruitSupplier::withoutGlobalScopes()->find($attributes['fruit_supplier_id'])->tenant_id,
            'fruit_supplier_id' => FruitSupplier::factory(),
            'year' => (int) now()->format('Y'),
            'month' => $this->faker->numberBetween(1, 12),
            'tons' => $this->faker->randomFloat(2, 100, 3000),
        ];
    }
}
