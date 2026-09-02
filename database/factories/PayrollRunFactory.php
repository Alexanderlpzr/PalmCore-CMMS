<?php

namespace Database\Factories;

use App\Domain\HumanResources\Enums\PayrollRunStatus;
use App\Models\PayrollRun;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Nómina de prueba',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => PayrollRunStatus::Borrador,
        ];
    }

    public function forPeriod(string $from, string $to, string $name = 'Nómina'): static
    {
        return $this->state(fn (): array => [
            'name' => $name,
            'period_start' => $from,
            'period_end' => $to,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => PayrollRunStatus::Cerrada,
            'calculated_at' => now(),
            'closed_at' => now(),
        ]);
    }
}
