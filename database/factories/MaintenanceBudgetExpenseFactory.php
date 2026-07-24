<?php

namespace Database\Factories;

use App\Domain\Maintenance\Enums\ExpenseCategory;
use App\Models\MaintenanceBudgetExpense;
use App\Models\Plant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceBudgetExpense>
 */
class MaintenanceBudgetExpenseFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'plant_id' => Plant::factory()->create(['tenant_id' => $tenant->id])->id,
            'expense_date' => now()->toDateString(),
            'amount' => $this->faker->numberBetween(50_000, 2_000_000),
            'category' => $this->faker->randomElement(ExpenseCategory::cases())->value,
            'description' => $this->faker->optional()->sentence(),
            'created_by' => null,
        ];
    }
}
