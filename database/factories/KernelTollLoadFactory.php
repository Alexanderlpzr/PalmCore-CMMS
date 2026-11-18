<?php

namespace Database\Factories;

use App\Models\KernelTollLoad;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Un cargue de almendra a maquila, ya procesado: 43 % de aceite y 51 % de torta, como
 * los del Excel.
 *
 * @extends Factory<KernelTollLoad>
 */
class KernelTollLoadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $processed = $this->faker->numberBetween(32_000, 36_000);

        return [
            'tenant_id' => Tenant::factory(),
            'loaded_on' => now()->subDays($this->faker->numberBetween(1, 30))->toDateString(),
            'plate' => strtoupper($this->faker->bothify('???###')),
            'departure_weight_tons' => round($processed / 1000, 2),
            'arrival_weight_tons' => round($processed / 1000 - 0.2, 2),
            'processed_kg' => $processed,
            'oil_kg' => (int) round($processed * 0.43),
            'cake_kg' => (int) round($processed * 0.51),
            'toll_amount' => $processed * 271,
            'freight_amount' => 4_900_000,
            'cake_price_per_ton' => 700_000,
        ];
    }
}
