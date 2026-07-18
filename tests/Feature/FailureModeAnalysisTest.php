<?php

use App\Domain\Maintenance\Enums\FailureConsequenceCategory;
use App\Domain\Maintenance\Enums\FailureMode;
use App\Models\Equipment;
use App\Models\FailureModeAnalysis;
use App\Models\MaintenancePlan;
use App\Models\Tenant;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->equipment = Equipment::factory()->for($this->tenant)->create();
});

it('casts failure_mode and consequence_category to their enums', function () {
    $analysis = FailureModeAnalysis::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'failure_mode' => FailureMode::Bearing->value,
        'consequence_category' => FailureConsequenceCategory::SafetyEnvironmental->value,
    ]);

    expect($analysis->failure_mode)->toBe(FailureMode::Bearing)
        ->and($analysis->consequence_category)->toBe(FailureConsequenceCategory::SafetyEnvironmental);
});

it('does not need a failure-finding task when the consequence is not hidden', function () {
    $analysis = FailureModeAnalysis::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'consequence_category' => FailureConsequenceCategory::Operational->value,
    ]);

    expect($analysis->isHidden())->toBeFalse()
        ->and($analysis->needsFailureFindingTask())->toBeFalse();
});

it('needs a failure-finding task when the consequence is hidden and no plan is linked', function () {
    $analysis = FailureModeAnalysis::factory()->hidden()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'failure_finding_plan_id' => null,
    ]);

    expect($analysis->isHidden())->toBeTrue()
        ->and($analysis->needsFailureFindingTask())->toBeTrue();
});

it('stops needing a failure-finding task once one is linked', function () {
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'is_failure_finding' => true,
    ]);

    $analysis = FailureModeAnalysis::factory()->hidden()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'failure_finding_plan_id' => $plan->id,
    ]);

    expect($analysis->needsFailureFindingTask())->toBeFalse()
        ->and($analysis->failureFindingPlan->is($plan))->toBeTrue();
});
