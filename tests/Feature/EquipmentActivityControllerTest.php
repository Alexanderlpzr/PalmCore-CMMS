<?php

use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\EquipmentPhoto;
use App\Models\MaintenancePlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

function activityApiSetup(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('test-token', ['equipment.read']);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return ['tenant' => $tenant, 'user' => $user, 'token' => $tokenResult->plainTextToken];
}

it('returns 404 for unknown equipment', function (): void {
    ['token' => $token] = activityApiSetup();

    $this->getJson('/api/v1/equipment/00000000-0000-0000-0000-000000000000/activity', [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ])->assertNotFound();
});

it('returns empty timeline for equipment with no events', function (): void {
    ['tenant' => $tenant, 'token' => $token] = activityApiSetup();

    $equipment = Equipment::factory()->for($tenant)->create();

    $res = $this->getJson("/api/v1/equipment/{$equipment->id}/activity", [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ])->assertOk();

    expect($res->json('data'))->toBeArray()->toBeEmpty();
    expect($res->json('meta.total'))->toBe(0);
});

it('includes work_order_created events in timeline', function (): void {
    ['tenant' => $tenant, 'token' => $token, 'user' => $user] = activityApiSetup();

    $equipment = Equipment::factory()->for($tenant)->create();
    WorkOrder::factory()->for($tenant)->create([
        'equipment_id' => $equipment->id,
        'created_by' => $user->id,
    ]);

    $res = $this->getJson("/api/v1/equipment/{$equipment->id}/activity", [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ])->assertOk();

    $types = collect($res->json('data'))->pluck('type');
    expect($types)->toContain('work_order_created');
});

it('includes preventive_executed event for plan-based closed WOs', function (): void {
    ['tenant' => $tenant, 'token' => $token, 'user' => $user] = activityApiSetup();

    $equipment = Equipment::factory()->for($tenant)->create();
    $plan = MaintenancePlan::factory()->for($tenant)->create(['equipment_id' => $equipment->id]);
    WorkOrder::factory()->for($tenant)->create([
        'equipment_id' => $equipment->id,
        'created_by' => $user->id,
        'maintenance_plan_id' => $plan->id,
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    $res = $this->getJson("/api/v1/equipment/{$equipment->id}/activity", [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ])->assertOk();

    $types = collect($res->json('data'))->pluck('type');
    expect($types)->toContain('preventive_executed');
    expect($types)->not->toContain('work_order_closed');
});

it('includes downtime and photo events in timeline', function (): void {
    ['tenant' => $tenant, 'token' => $token] = activityApiSetup();

    $equipment = Equipment::factory()->for($tenant)->create();
    EquipmentDowntimeEvent::factory()->for($tenant)->create(['equipment_id' => $equipment->id]);
    EquipmentPhoto::factory()->for($tenant)->create(['equipment_id' => $equipment->id]);

    $res = $this->getJson("/api/v1/equipment/{$equipment->id}/activity", [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ])->assertOk();

    $types = collect($res->json('data'))->pluck('type');
    expect($types)->toContain('downtime');
    expect($types)->toContain('photo_added');
});

it('events are sorted descending by date', function (): void {
    ['tenant' => $tenant, 'token' => $token] = activityApiSetup();

    $equipment = Equipment::factory()->for($tenant)->create();
    EquipmentPhoto::factory()->for($tenant)->count(4)->create(['equipment_id' => $equipment->id]);

    $res = $this->getJson("/api/v1/equipment/{$equipment->id}/activity", [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ])->assertOk();

    $dates = collect($res->json('data'))->pluck('at');
    $sorted = $dates->sortDesc()->values();
    expect($dates->values()->all())->toBe($sorted->all());
});

it('requires equipment.read ability', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('bad-token', ['work-orders.read']);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    $equipment = Equipment::factory()->for($tenant)->create();

    $this->getJson("/api/v1/equipment/{$equipment->id}/activity", [
        'Authorization' => 'Bearer '.$tokenResult->plainTextToken,
        'Accept' => 'application/json',
    ])->assertForbidden();
});

it('paginates timeline correctly', function (): void {
    ['tenant' => $tenant, 'token' => $token] = activityApiSetup();

    $equipment = Equipment::factory()->for($tenant)->create();
    EquipmentPhoto::factory()->for($tenant)->count(10)->create(['equipment_id' => $equipment->id]);

    $res = $this->getJson("/api/v1/equipment/{$equipment->id}/activity?per_page=3", [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ])->assertOk();

    expect($res->json('data'))->toHaveCount(3);
    expect($res->json('meta.total'))->toBe(10);
    expect($res->json('meta.has_more'))->toBeTrue();
});
