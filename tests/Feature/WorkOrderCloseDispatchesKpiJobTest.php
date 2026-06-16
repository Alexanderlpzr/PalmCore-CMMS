<?php

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Domain\Reliability\Services\EquipmentKpiService;
use App\Jobs\RecalculateEquipmentKpisJob;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Regression tests for the verified → closed transition.
 *
 * Bug: dispatching RecalculateEquipmentKpisJob (ShouldBeUnique) a second time
 * for the same equipment while the first lock is still held caused
 * SQLSTATE[25P02] ("In failed sql transaction") in PostgreSQL. The database
 * cache driver's lock acquisition does INSERT + UPDATE; a failed INSERT aborts
 * the PostgreSQL connection, and the subsequent UPDATE in the catch block fails.
 *
 * Fix: RecalculateEquipmentKpisJob::uniqueVia() now returns the file cache
 * driver, whose acquire() returns false on conflict without aborting any
 * database transaction.
 */
it('closes a work order from the verified state without throwing', function () {
    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'status' => WorkOrderStatus::Verified->value,
        'verified_at' => now(),
        'verified_by' => $user->id,
    ]);

    $closed = $service->transition($wo, WorkOrderStatus::Closed, $user);

    expect($closed->status)->toBe(WorkOrderStatus::Closed);
    expect($closed->closed_at)->not->toBeNull();
});

it('dispatches RecalculateEquipmentKpisJob when a work order is closed', function () {
    Queue::fake();

    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'status' => WorkOrderStatus::Verified->value,
    ]);

    $service->transition($wo, WorkOrderStatus::Closed, $user);

    Queue::assertPushed(
        RecalculateEquipmentKpisJob::class,
        fn ($job) => $job->equipmentId === $equipment->id,
    );
});

it('does not throw SQLSTATE[25P02] when KPI job is dispatched twice for the same equipment', function () {
    // Simulates the completed → verified → closed sequence: both "completed" and
    // "closed" trigger a dispatch of RecalculateEquipmentKpisJob for the same
    // equipment. Before the fix, the second dispatch caused SQLSTATE[25P02] via
    // DatabaseLock::acquire() (INSERT fails → PostgreSQL connection aborts →
    // UPDATE in catch fails).
    $equipmentId = (string) Str::uuid();

    // First dispatch — acquires the unique job lock
    RecalculateEquipmentKpisJob::dispatch($equipmentId);

    // Second dispatch — with database cache driver (before fix) this threw
    // SQLSTATE[25P02]; with file cache driver it silently skips the duplicate.
    // Test passes if no exception is raised.
    RecalculateEquipmentKpisJob::dispatch($equipmentId);

    expect(true)->toBeTrue();
});

it('KPI recalculation completes without exception on the full completed-then-closed cycle', function () {
    Queue::fake();

    $service = app(WorkOrderService::class);
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'status' => WorkOrderStatus::InProgress->value,
        'actual_start_at' => now()->subHours(2),
        'started_at' => now()->subHours(2),
    ]);

    // in_progress → completed (first KPI dispatch)
    $wo = $service->transition($wo, WorkOrderStatus::Completed, $user);
    // completed → verified
    $wo = $service->transition($wo, WorkOrderStatus::Verified, $user);
    // verified → closed (second KPI dispatch — would throw 25P02 before fix)
    $closed = $service->transition($wo, WorkOrderStatus::Closed, $user);

    expect($closed->status)->toBe(WorkOrderStatus::Closed);
    // The "completed" transition enqueues the job and holds the lock for 300 s.
    // The "closed" transition coalesces into that lock (silently not re-queued).
    // One job is sufficient: it will recalculate KPIs after being processed.
    Queue::assertPushed(RecalculateEquipmentKpisJob::class, 1);
});

it('KPI job handle() runs without exception for a valid equipment', function () {
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $job = new RecalculateEquipmentKpisJob($equipment->id);
    $job->handle(app(EquipmentKpiService::class));

    // Test passes if handle() completes without throwing
    expect(true)->toBeTrue();
});
