<?php

namespace Database\Factories;

use App\Domain\Safety\Enums\FindingStatus;
use App\Domain\Safety\Enums\ResponsibleArea;
use App\Models\SstFinding;
use App\Models\SstFindingTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SstFindingTask>
 */
class SstFindingTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sst_finding_id' => SstFinding::factory(),
            'tenant_id' => fn (array $attributes): string => SstFinding::withoutGlobalScopes()->find($attributes['sst_finding_id'])->tenant_id,
            'area' => ResponsibleArea::Proceso->value,
            'status' => FindingStatus::Pendiente->value,
        ];
    }
}
