<?php

use App\Models\WorkOrder;

it('reports a positive variance and percentage when over budget', function () {
    $wo = WorkOrder::factory()->make([
        'estimated_cost' => 100000,
        'actual_cost_total' => 130000,
    ]);

    expect($wo->costVariance())->toBe(30000.0)
        ->and($wo->costVariancePercentage())->toBe(30.0);
});

it('reports a negative variance when under budget (savings)', function () {
    $wo = WorkOrder::factory()->make([
        'estimated_cost' => 100000,
        'actual_cost_total' => 80000,
    ]);

    expect($wo->costVariance())->toBe(-20000.0)
        ->and($wo->costVariancePercentage())->toBe(-20.0);
});

it('returns null variance when the estimate is missing', function () {
    $wo = WorkOrder::factory()->make([
        'estimated_cost' => null,
        'actual_cost_total' => 50000,
    ]);

    expect($wo->costVariance())->toBeNull()
        ->and($wo->costVariancePercentage())->toBeNull();
});

it('returns null variance when the actual total is missing', function () {
    $wo = WorkOrder::factory()->make([
        'estimated_cost' => 100000,
        'actual_cost_total' => null,
    ]);

    expect($wo->costVariance())->toBeNull();
});

it('returns a variance but null percentage when the estimate is zero', function () {
    $wo = WorkOrder::factory()->make([
        'estimated_cost' => 0,
        'actual_cost_total' => 50000,
    ]);

    expect($wo->costVariance())->toBe(50000.0)
        ->and($wo->costVariancePercentage())->toBeNull();
});
