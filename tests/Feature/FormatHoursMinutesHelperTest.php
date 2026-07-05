<?php

it('formats hours and minutes combined', function () {
    expect(format_hours_minutes(2.5))->toBe('2h 30min');
});

it('formats whole hours without minutes', function () {
    expect(format_hours_minutes(3.0))->toBe('3h');
});

it('formats sub-hour durations as minutes only', function () {
    expect(format_hours_minutes(0.75))->toBe('45min');
});

it('returns null for null, zero, or negative input', function () {
    expect(format_hours_minutes(null))->toBeNull()
        ->and(format_hours_minutes(0))->toBeNull()
        ->and(format_hours_minutes(-1.5))->toBeNull();
});

it('rounds to the nearest minute', function () {
    // 1.0083 hours = 1h 0.5min, rounds to 1h.
    expect(format_hours_minutes(1.0083))->toBe('1h');
});
