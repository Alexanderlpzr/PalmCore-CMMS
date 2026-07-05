<?php

use App\Domain\Analytics\Support\DashboardPeriod;

it('resolve returns [null, null] for the default preset', function () {
    expect(DashboardPeriod::resolve(null))->toBe([null, null])
        ->and(DashboardPeriod::resolve(['preset' => 'last_12_months']))->toBe([null, null]);
});

it('resolve returns the full calendar year for the year preset', function () {
    [$from, $to] = DashboardPeriod::resolve(['preset' => 'year', 'year' => 2025]);

    expect($from->format('Y-m-d'))->toBe('2025-01-01')
        ->and($to->format('Y-m-d'))->toBe('2025-12-01');
});

it('resolve returns a single month for the month preset', function () {
    [$from, $to] = DashboardPeriod::resolve(['preset' => 'month', 'year' => 2025, 'month' => 3]);

    expect($from->format('Y-m-d'))->toBe('2025-03-01')
        ->and($to->format('Y-m-d'))->toBe('2025-03-01');
});

it('resolve returns a month range for the range preset (e.g. January to October)', function () {
    [$from, $to] = DashboardPeriod::resolve([
        'preset' => 'range', 'range_year' => 2025, 'range_from_month' => 1, 'range_to_month' => 10,
    ]);

    expect($from->format('Y-m-d'))->toBe('2025-01-01')
        ->and($to->format('Y-m-d'))->toBe('2025-10-01');
});

it('resolve swaps from/to when the range is entered backwards', function () {
    [$from, $to] = DashboardPeriod::resolve([
        'preset' => 'range', 'range_year' => 2025, 'range_from_month' => 10, 'range_to_month' => 1,
    ]);

    expect($from->format('Y-m-d'))->toBe('2025-01-01')
        ->and($to->format('Y-m-d'))->toBe('2025-10-01');
});

it('label describes each preset in Spanish', function () {
    expect(DashboardPeriod::label(null))->toBe('últimos 12 meses')
        ->and(DashboardPeriod::label(['preset' => 'year', 'year' => 2025]))->toBe('año 2025')
        ->and(DashboardPeriod::label(['preset' => 'month', 'year' => 2025, 'month' => 3]))->toBe('Marzo 2025')
        ->and(DashboardPeriod::label([
            'preset' => 'range', 'range_year' => 2025, 'range_from_month' => 1, 'range_to_month' => 10,
        ]))->toBe('Enero – Octubre 2025');
});

it('yearOptions returns the current year and the requested span going backwards', function () {
    $options = DashboardPeriod::yearOptions(3);

    expect($options)->toHaveCount(3)
        ->and(array_key_first($options))->toBe((int) now()->year);
});

it('monthOptions returns all 12 months', function () {
    expect(DashboardPeriod::monthOptions())->toHaveCount(12);
});
