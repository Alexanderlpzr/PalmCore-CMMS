<?php

use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;

/**
 * Regression tests for generateWorkOrderNumber().
 *
 * The global sequence counter is determined by the numeric suffix of existing
 * WO numbers, NOT by the lexicographic order of equipment codes. A WO created
 * for equipment "ZZZ" must carry the correct global next sequence even when an
 * earlier WO for equipment "AAA" has a lower code but a higher sequence.
 *
 * Bug: the original implementation used orderByDesc('work_order_number'), which
 * sorts the full VARCHAR. 'ZZZ-000001' sorts HIGHER than 'AAA-000002', causing
 * the generator to extract suffix 1 and produce sequence 2 — a duplicate when
 * OT-YEAR-AAA-000002 already exists.
 */
it('sequences are global across different equipment codes — lexicographic code order does not affect the counter', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $equipZZZ = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => 'ZZZ-EQ']);
    $equipAAA = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => 'AAA-EQ']);

    // Sequence 1 goes to ZZZ-EQ
    $wo1 = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipZZZ->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Primera OT para ZZZ',
        'description' => 'desc',
    ], $user);

    expect($wo1->work_order_number)->toBe('OT-'.date('Y').'-ZZZ-EQ-000001');

    // Sequence 2 must be 2 — NOT 1 — even though 'AAA' < 'ZZZ' lexicographically.
    // The original bug returned 1 here (found ZZZ-EQ-000001 via string DESC sort,
    // extracted suffix 000001, generated 000002 → unique violation on retry).
    $wo2 = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipAAA->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Segunda OT para AAA',
        'description' => 'desc',
    ], $user);

    expect($wo2->work_order_number)->toBe('OT-'.date('Y').'-AAA-EQ-000002');

    // Sequence 3 back on ZZZ-EQ must still be 3
    $wo3 = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipZZZ->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Tercera OT para ZZZ',
        'description' => 'desc',
    ], $user);

    expect($wo3->work_order_number)->toBe('OT-'.date('Y').'-ZZZ-EQ-000003');
});

it('no unique constraint violation when many equipment codes coexist', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $codes = ['TES-00', 'EQ-SLB', 'E2E-PRE-001', 'AAA-EQ', 'ZZZ-EQ'];
    $numbers = [];

    foreach ($codes as $code) {
        $equip = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => $code]);
        $wo = $service->create([
            'tenant_id' => $tenant->id,
            'equipment_id' => $equip->id,
            'work_order_type' => 'corrective',
            'priority' => 'p3_medium',
            'title' => "OT para {$code}",
            'description' => 'desc',
        ], $user);
        $numbers[] = $wo->work_order_number;
    }

    // All numbers must be unique
    expect(array_unique($numbers))->toHaveCount(count($codes));

    // Sequences must be strictly 1..N (no gaps, no duplicates)
    $sequences = array_map(fn ($n) => (int) substr($n, -6), $numbers);
    sort($sequences);
    expect($sequences)->toBe(range(1, count($codes)));
});
