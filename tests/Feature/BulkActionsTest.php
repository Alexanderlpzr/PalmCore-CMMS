<?php

use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionSeeder;

function bulkUser(array $abilities, bool $superAdmin = true): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true, 'is_super_admin' => $superAdmin]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('bulk', $abilities);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return ['tenant' => $tenant, 'user' => $user, 'token' => $tokenResult->plainTextToken];
}

function bulkHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

// ── Work orders ───────────────────────────────────────────────────────────────

it('PATCH /work-orders/bulk cancels several work orders (full success)', function () {
    ['tenant' => $tenant, 'token' => $token] = bulkUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $wos = WorkOrder::factory()->count(3)->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'status' => 'planned']);

    $response = $this->withHeaders(bulkHeaders($token))->patchJson('/api/v1/work-orders/bulk', [
        'ids' => $wos->pluck('id')->all(),
        'action' => 'cancel',
    ]);

    $response->assertOk()
        ->assertJsonPath('succeeded', 3)
        ->assertJsonPath('failed', []);

    foreach ($wos as $wo) {
        $this->assertDatabaseHas('work_orders', ['id' => $wo->id, 'status' => 'cancelled']);
    }
});

it('PATCH /work-orders/bulk reports per-item failures without aborting the batch (partial success)', function () {
    ['tenant' => $tenant, 'token' => $token] = bulkUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $cancellable = WorkOrder::factory()->count(2)->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'status' => 'planned']);
    $terminal = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'status' => 'closed']);

    $response = $this->withHeaders(bulkHeaders($token))->patchJson('/api/v1/work-orders/bulk', [
        'ids' => [...$cancellable->pluck('id')->all(), $terminal->id],
        'action' => 'cancel',
    ]);

    $response->assertOk()
        ->assertJsonPath('succeeded', 2);

    expect($response->json('failed'))->toHaveCount(1)
        ->and($response->json('failed.0.id'))->toBe($terminal->id);

    $this->assertDatabaseHas('work_orders', ['id' => $terminal->id, 'status' => 'closed']);
});

it('PATCH /work-orders/bulk enforces tenant isolation (cross-tenant ids fail, not mutate)', function () {
    ['token' => $token] = bulkUser(['work-orders.write']);
    $otherTenant = Tenant::factory()->create();
    $otherEquipment = Equipment::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherWo = WorkOrder::factory()->create(['tenant_id' => $otherTenant->id, 'equipment_id' => $otherEquipment->id, 'status' => 'planned']);

    $response = $this->withHeaders(bulkHeaders($token))->patchJson('/api/v1/work-orders/bulk', [
        'ids' => [$otherWo->id],
        'action' => 'cancel',
    ]);

    $response->assertOk()->assertJsonPath('succeeded', 0);
    expect($response->json('failed.0.id'))->toBe($otherWo->id);
    $this->assertDatabaseHas('work_orders', ['id' => $otherWo->id, 'status' => 'planned']);
});

it('PATCH /work-orders/bulk changes priority', function () {
    ['tenant' => $tenant, 'token' => $token] = bulkUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $wos = WorkOrder::factory()->count(2)->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'status' => 'planned', 'priority' => 'p4_low']);

    $response = $this->withHeaders(bulkHeaders($token))->patchJson('/api/v1/work-orders/bulk', [
        'ids' => $wos->pluck('id')->all(),
        'action' => 'set_priority',
        'value' => 'p1_critical',
    ]);

    $response->assertOk()->assertJsonPath('succeeded', 2);
    $this->assertDatabaseHas('work_orders', ['id' => $wos->first()->id, 'priority' => 'p1_critical']);
});

it('PATCH /work-orders/bulk rejects an invalid priority value', function () {
    ['tenant' => $tenant, 'token' => $token] = bulkUser(['work-orders.write']);
    $wo = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    $this->withHeaders(bulkHeaders($token))->patchJson('/api/v1/work-orders/bulk', [
        'ids' => [$wo->id],
        'action' => 'set_priority',
        'value' => 'not-a-priority',
    ])->assertStatus(422);
});

it('PATCH /work-orders/bulk is forbidden without work-orders.write ability', function () {
    ['token' => $token] = bulkUser(['work-orders.read']);

    $this->withHeaders(bulkHeaders($token))->patchJson('/api/v1/work-orders/bulk', [
        'ids' => ['00000000-0000-7000-8000-000000000000'],
        'action' => 'cancel',
    ])->assertForbidden();
});

it('PATCH /work-orders/bulk enforces the policy: a user without permission cannot mutate', function () {
    $this->seed(PermissionSeeder::class);
    ['tenant' => $tenant, 'token' => $token] = bulkUser(['work-orders.write'], superAdmin: false);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $wo = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'status' => 'planned']);

    $response = $this->withHeaders(bulkHeaders($token))->patchJson('/api/v1/work-orders/bulk', [
        'ids' => [$wo->id],
        'action' => 'cancel',
    ]);

    $response->assertOk()->assertJsonPath('succeeded', 0);
    expect($response->json('failed.0.error'))->toBe('No autorizado');
    $this->assertDatabaseHas('work_orders', ['id' => $wo->id, 'status' => 'planned']);
});

it('PATCH /work-orders/bulk requires authentication', function () {
    $this->patchJson('/api/v1/work-orders/bulk', ['ids' => ['x'], 'action' => 'cancel'])->assertUnauthorized();
});

// ── Maintenance requests ──────────────────────────────────────────────────────

it('PATCH /maintenance-requests/bulk approves requests under review', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = bulkUser(['maintenance-requests.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $mrs = MaintenanceRequest::factory()->count(2)->create([
        'tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'status' => 'under_review', 'created_by' => $user->id,
    ]);

    $response = $this->withHeaders(bulkHeaders($token))->patchJson('/api/v1/maintenance-requests/bulk', [
        'ids' => $mrs->pluck('id')->all(),
        'action' => 'approve',
    ]);

    $response->assertOk()->assertJsonPath('succeeded', 2);
    $this->assertDatabaseHas('maintenance_requests', ['id' => $mrs->first()->id, 'status' => 'approved']);
});

it('PATCH /maintenance-requests/bulk changes priority', function () {
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = bulkUser(['maintenance-requests.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $mr = MaintenanceRequest::factory()->create([
        'tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'status' => 'submitted', 'created_by' => $user->id, 'priority' => 'p4_low',
    ]);

    $this->withHeaders(bulkHeaders($token))->patchJson('/api/v1/maintenance-requests/bulk', [
        'ids' => [$mr->id],
        'action' => 'set_priority',
        'value' => 'p2_high',
    ])->assertOk()->assertJsonPath('succeeded', 1);

    $this->assertDatabaseHas('maintenance_requests', ['id' => $mr->id, 'priority' => 'p2_high']);
});

// ── Equipment ─────────────────────────────────────────────────────────────────

it('PATCH /equipment/bulk changes status', function () {
    ['tenant' => $tenant, 'token' => $token] = bulkUser(['equipment.write']);
    $equipment = Equipment::factory()->count(3)->create(['tenant_id' => $tenant->id, 'status' => 'active']);

    $response = $this->withHeaders(bulkHeaders($token))->patchJson('/api/v1/equipment/bulk', [
        'ids' => $equipment->pluck('id')->all(),
        'action' => 'set_status',
        'value' => 'under_maintenance',
    ]);

    $response->assertOk()->assertJsonPath('succeeded', 3);
    $this->assertDatabaseHas('equipment', ['id' => $equipment->first()->id, 'status' => 'under_maintenance']);
});

it('PATCH /equipment/bulk changes criticality', function () {
    ['tenant' => $tenant, 'token' => $token] = bulkUser(['equipment.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'criticality' => 'low']);

    $this->withHeaders(bulkHeaders($token))->patchJson('/api/v1/equipment/bulk', [
        'ids' => [$equipment->id],
        'action' => 'set_criticality',
        'value' => 'critical',
    ])->assertOk()->assertJsonPath('succeeded', 1);

    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'criticality' => 'critical']);
});

it('PATCH /equipment/bulk rejects an invalid status value', function () {
    ['tenant' => $tenant, 'token' => $token] = bulkUser(['equipment.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $this->withHeaders(bulkHeaders($token))->patchJson('/api/v1/equipment/bulk', [
        'ids' => [$equipment->id],
        'action' => 'set_status',
        'value' => 'bogus',
    ])->assertStatus(422);
});

it('PATCH /equipment/bulk is forbidden without equipment.write ability', function () {
    ['token' => $token] = bulkUser(['equipment.read']);

    $this->withHeaders(bulkHeaders($token))->patchJson('/api/v1/equipment/bulk', [
        'ids' => ['00000000-0000-7000-8000-000000000000'],
        'action' => 'set_status',
        'value' => 'active',
    ])->assertForbidden();
});
