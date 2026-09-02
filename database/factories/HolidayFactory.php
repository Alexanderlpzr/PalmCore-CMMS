<?php

namespace Database\Factories;

use App\Models\Holiday;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'holiday_date' => now()->toDateString(),
            'name' => 'Festivo de prueba',
            'is_national' => true,
        ];
    }

    public function on(string $date, string $name = 'Festivo'): static
    {
        return $this->state(fn (): array => [
            'holiday_date' => $date,
            'name' => $name,
        ]);
    }
}
