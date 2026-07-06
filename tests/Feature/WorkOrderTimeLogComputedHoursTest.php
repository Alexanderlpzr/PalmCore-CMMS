<?php

use App\Models\WorkOrderTimeLog;

it('computedHours uses the stored hours value when present', function () {
    $log = WorkOrderTimeLog::factory()->create(['hours' => 3.5]);

    expect($log->computedHours())->toBe(3.5);
});

it('computedHours is zero for a still-open log', function () {
    $log = WorkOrderTimeLog::factory()->open()->create();

    expect($log->computedHours())->toBe(0.0);
});

// Regression: Carbon 3's diffInMinutes() returns a signed difference by
// default, so this fallback (only reached when "hours" wasn't precomputed at
// close time) used to return a negative duration.
it('computedHours falls back to a positive started_at/ended_at interval when hours is null', function () {
    $log = WorkOrderTimeLog::factory()->create([
        'hours' => null,
        'started_at' => '2026-07-06 09:00:00',
        'ended_at' => '2026-07-06 10:30:00',
    ]);

    expect($log->computedHours())->toBe(1.5);
});
