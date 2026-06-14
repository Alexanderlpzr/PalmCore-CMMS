<?php

use App\Jobs\RecalculateEquipmentKpisJob;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\EquipmentKpi;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;

// ── Helpers ───────────────────────────────────────────────────────────────────

function openDowntime(Equipment $equipment): EquipmentDowntimeEvent
{
    return EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $equipment->tenant_id,
        'equipment_id' => $equipment->id,
        'was_planned' => false,
        'started_at' => Carbon::now()->subHours(2),
        'ended_at' => null,
        'duration_minutes' => null,
    ]);
}

function dtEquipment(): Equipment
{
    $tenant = Tenant::factory()->create();

    return Equipment::factory()->create(['tenant_id' => $tenant->id]);
}

// ── Dispatch on null → timestamp ─────────────────────────────────────────────

it('dispatches job when ended_at changes from null to a timestamp', function () {
    Bus::fake();

    $equipment = dtEquipment();
    $event = openDowntime($equipment);

    $event->update(['ended_at' => Carbon::now(), 'duration_minutes' => 120]);

    Bus::assertDispatched(RecalculateEquipmentKpisJob::class, function ($job) use ($equipment) {
        return $job->equipmentId === $equipment->id;
    });
});

// ── No dispatch when ended_at already had a value ────────────────────────────

it('does not dispatch job when ended_at was already set and is updated again', function () {
    Bus::fake();

    $equipment = dtEquipment();

    $event = EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $equipment->tenant_id,
        'equipment_id' => $equipment->id,
        'started_at' => Carbon::now()->subHours(3),
        'ended_at' => Carbon::now()->subHours(1),
        'duration_minutes' => 120,
    ]);

    $event->update(['ended_at' => Carbon::now()]);

    Bus::assertNotDispatched(RecalculateEquipmentKpisJob::class);
});

// ── No dispatch on unrelated field changes ───────────────────────────────────

it('does not dispatch job when only notes change on an open event', function () {
    Bus::fake();

    $equipment = dtEquipment();
    $event = openDowntime($equipment);

    $event->update(['notes' => 'Updated notes']);

    Bus::assertNotDispatched(RecalculateEquipmentKpisJob::class);
});

it('does not dispatch job when only notes change on a closed event', function () {
    Bus::fake();

    $equipment = dtEquipment();

    $event = EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $equipment->tenant_id,
        'equipment_id' => $equipment->id,
        'started_at' => Carbon::now()->subHours(3),
        'ended_at' => Carbon::now()->subHours(1),
        'duration_minutes' => 120,
    ]);

    $event->update(['notes' => 'Updated notes']);

    Bus::assertNotDispatched(RecalculateEquipmentKpisJob::class);
});

// ── markStale is called ───────────────────────────────────────────────────────

it('marks KPI stale when downtime event is closed', function () {
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentKpi::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'is_stale' => false,
    ]);

    $event = openDowntime($equipment);

    Bus::fake();

    $event->update(['ended_at' => Carbon::now(), 'duration_minutes' => 120]);

    expect(EquipmentKpi::withoutGlobalScopes()
        ->where('equipment_id', $equipment->id)
        ->value('is_stale')
    )->toBeTrue();
});

// ── Scheduler ─────────────────────────────────────────────────────────────────

it('nightly scheduler is registered and runs at 02:00', function () {
    $events = app(Schedule::class)->events();

    $jobEvents = collect($events)->filter(function ($event) {
        return $event->expression === '0 2 * * *';
    });

    expect($jobEvents->count())->toBeGreaterThan(0);
});
