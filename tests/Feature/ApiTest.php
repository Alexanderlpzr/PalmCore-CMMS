<?php

use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\EquipmentKpi;
use App\Models\MaintenanceRequest;
use App\Models\PersonalAccessToken;
use App\Models\Plant;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use App\Models\WorkOrder;

// ── Helpers ───────────────────────────────────────────────────────────────────

function apiTenantWithUser(array $abilities = ['*']): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('test-token', $abilities);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return [
        'tenant' => $tenant,
        'user' => $user,
        'token' => $tokenResult->plainTextToken,
    ];
}

function apiHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

// ── Authentication & Middleware ───────────────────────────────────────────────

it('unauthenticated requests to protected API routes return 401', function () {
    $response = $this->getJson('/api/v1/equipment');

    $response->assertUnauthorized();
});

it('token without tenant_id returns 403 from ResolveApiTenant', function () {
    $user = User::factory()->create(['is_active' => true]);
    $tokenResult = $user->createToken('no-tenant-token', ['*']);
    // Deliberately NOT setting tenant_id

    $response = $this->withHeaders(apiHeaders($tokenResult->plainTextToken))
        ->getJson('/api/v1/equipment');

    $response->assertForbidden();
});

it('token scoped to a tenant resolves tenant context', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser();

    Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment');

    $response->assertOk();
});

// ── Token creation (POST /api/v1/tokens) ─────────────────────────────────────

it('POST /api/v1/tokens creates a token with tenant_id', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true, 'password' => bcrypt('password123')]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $response = $this->postJson('/api/v1/tokens', [
        'email' => $user->email,
        'password' => 'password123',
        'tenant_slug' => $tenant->slug,
        'token_name' => 'Power BI',
        'abilities' => ['equipment.read'],
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'abilities', 'tenant', 'expires_at']);

    $this->assertDatabaseHas('personal_access_tokens', [
        'name' => 'Power BI',
        'tenant_id' => $tenant->id,
    ]);
});

it('POST /api/v1/tokens returns 401 for wrong password', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true, 'password' => bcrypt('correct')]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $response = $this->postJson('/api/v1/tokens', [
        'email' => $user->email,
        'password' => 'wrong',
        'tenant_slug' => $tenant->slug,
        'token_name' => 'Test',
    ]);

    $response->assertUnauthorized();
});

it('POST /api/v1/tokens returns 403 when user does not belong to tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true, 'password' => bcrypt('password')]);
    // Not attached to tenant

    $response = $this->postJson('/api/v1/tokens', [
        'email' => $user->email,
        'password' => 'password',
        'tenant_slug' => $tenant->slug,
        'token_name' => 'Test',
    ]);

    $response->assertForbidden();
});

// ── Token revocation (DELETE /api/v1/tokens/{id}) ────────────────────────────

it('authenticated users can revoke their own tokens', function () {
    ['user' => $user, 'token' => $token] = apiTenantWithUser();

    $tokenModel = $user->tokens()->first();

    $response = $this->withHeaders(apiHeaders($token))
        ->deleteJson('/api/v1/tokens/'.$tokenModel->id);

    $response->assertOk();
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenModel->id]);
});

it('users cannot revoke tokens belonging to other users', function () {
    ['token' => $token] = apiTenantWithUser();

    $otherUser = User::factory()->create();
    $otherToken = $otherUser->createToken('other')->accessToken;

    $response = $this->withHeaders(apiHeaders($token))
        ->deleteJson('/api/v1/tokens/'.$otherToken->id);

    $response->assertNotFound();
});

// ── Tenant isolation ──────────────────────────────────────────────────────────

it('equipment index only returns equipment from the token tenant', function () {
    ['tenant' => $tenantA, 'token' => $tokenA] = apiTenantWithUser();
    $tenantB = Tenant::factory()->create();

    Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    Equipment::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

    $response = $this->withHeaders(apiHeaders($tokenA))->getJson('/api/v1/equipment');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('work orders index enforces tenant isolation', function () {
    ['tenant' => $tenantA, 'token' => $tokenA] = apiTenantWithUser();
    $tenantB = Tenant::factory()->create();

    $equipA = Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);

    WorkOrder::factory()->create(['tenant_id' => $tenantA->id, 'equipment_id' => $equipA->id]);
    WorkOrder::factory()->count(2)->create(['tenant_id' => $tenantB->id, 'equipment_id' => $equipB->id]);

    $response = $this->withHeaders(apiHeaders($tokenA))->getJson('/api/v1/work-orders');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

// ── Token ability enforcement ─────────────────────────────────────────────────

it('token with equipment.read can access equipment endpoint', function () {
    ['token' => $token] = apiTenantWithUser(['equipment.read']);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment');

    $response->assertOk();
});

it('token without equipment.read is forbidden on equipment endpoint', function () {
    ['token' => $token] = apiTenantWithUser(['work-orders.read']);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment');

    $response->assertForbidden();
});

it('wildcard token can access all endpoints', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['*']);

    Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment')->assertOk();
    $this->withHeaders(apiHeaders($token))->getJson('/api/v1/work-orders')->assertOk();
    $this->withHeaders(apiHeaders($token))->getJson('/api/v1/plants')->assertOk();
});

// ── Equipment endpoints ───────────────────────────────────────────────────────

it('GET /api/v1/equipment returns expected JSON structure', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser();
    $plant = Plant::factory()->create(['tenant_id' => $tenant->id]);
    Equipment::factory()->create(['tenant_id' => $tenant->id, 'plant_id' => $plant->id]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'code', 'name', 'status', 'is_active', 'plant', 'created_at']]]);
});

it('GET /api/v1/equipment/{id} returns 404 for equipment of another tenant', function () {
    ['token' => $tokenA] = apiTenantWithUser();
    $tenantB = Tenant::factory()->create();
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);

    $response = $this->withHeaders(apiHeaders($tokenA))->getJson('/api/v1/equipment/'.$equipB->id);

    $response->assertNotFound();
});

it('GET /api/v1/equipment supports status filter', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser();

    Equipment::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
    Equipment::factory()->create(['tenant_id' => $tenant->id, 'status' => 'inactive']);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment?status=active');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

// ── Work orders ───────────────────────────────────────────────────────────────

it('GET /api/v1/work-orders returns expected JSON structure', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/work-orders');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'work_order_number', 'status', 'equipment', 'created_at']]]);
});

// ── Downtime events ───────────────────────────────────────────────────────────

it('GET /api/v1/downtime-events filters by was_planned', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['downtime.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentDowntimeEvent::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'was_planned' => true]);
    EquipmentDowntimeEvent::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'was_planned' => false]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/downtime-events?was_planned=false');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

// ── Reliability KPIs ──────────────────────────────────────────────────────────

it('GET /api/v1/reliability/kpis returns kpi structure', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['reliability.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    EquipmentKpi::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/reliability/kpis');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'equipment', 'availability_percentage', 'mtbf_hours']]]);
});

// ── Spare parts ───────────────────────────────────────────────────────────────

it('GET /api/v1/inventory/spare-parts enforces tenant isolation', function () {
    ['token' => $tokenA] = apiTenantWithUser(['inventory.read']);
    $tenantB = Tenant::factory()->create();

    SparePart::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

    $response = $this->withHeaders(apiHeaders($tokenA))->getJson('/api/v1/inventory/spare-parts');

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

// ── Plants & Areas ────────────────────────────────────────────────────────────

it('GET /api/v1/plants returns plants for the token tenant only', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['plants.read']);
    Plant::factory()->count(2)->create(['tenant_id' => $tenant->id]);
    $otherTenant = Tenant::factory()->create();
    Plant::factory()->create(['tenant_id' => $otherTenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/plants');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('GET /api/v1/areas includes plant in response', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['areas.read']);
    $plant = Plant::factory()->create(['tenant_id' => $tenant->id]);
    Area::factory()->create(['tenant_id' => $tenant->id, 'plant_id' => $plant->id]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/areas');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'code', 'name', 'plant']]]);
});

// ── Pagination ────────────────────────────────────────────────────────────────

it('cursor pagination is used by default', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser();
    Equipment::factory()->count(5)->create(['tenant_id' => $tenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment?per_page=2');

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);
});

it('offset pagination is used when page param is present', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser();
    Equipment::factory()->count(5)->create(['tenant_id' => $tenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment?page=1&per_page=2');

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);
});

// ── PersonalAccessToken model ─────────────────────────────────────────────────

it('PersonalAccessToken stores tenant_id correctly', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $tokenResult = $user->createToken('test');
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    $stored = PersonalAccessToken::find($tokenResult->accessToken->id);

    expect($stored->tenant_id)->toBe($tenant->id)
        ->and($stored->tenant)->not->toBeNull();
});

// ── POST /api/v1/work-orders ──────────────────────────────────────────────────

it('POST /api/v1/work-orders creates a work order and returns 201', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->postJson('/api/v1/work-orders', [
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p2_high',
        'title' => 'Falla en bomba hidráulica',
        'description' => 'Se detectó pérdida de presión.',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'work_order_number', 'status', 'equipment']])
        ->assertJsonPath('data.status', 'draft');

    expect($response->headers->has('Location'))->toBeTrue();

    $this->assertDatabaseHas('work_orders', [
        'equipment_id' => $equipment->id,
        'tenant_id' => $tenant->id,
        'work_order_type' => 'corrective',
    ]);
});

it('POST /api/v1/work-orders returns 403 without work-orders.write ability', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->postJson('/api/v1/work-orders', [
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p2_high',
        'title' => 'Test',
        'description' => 'Test',
    ]);

    $response->assertForbidden();
});

it('POST /api/v1/work-orders returns 422 for invalid equipment_id', function () {
    ['token' => $token] = apiTenantWithUser(['work-orders.write']);

    $response = $this->withHeaders(apiHeaders($token))->postJson('/api/v1/work-orders', [
        'equipment_id' => 'non-existent-uuid',
        'work_order_type' => 'corrective',
        'priority' => 'p2_high',
        'title' => 'Test',
        'description' => 'Test',
    ]);

    $response->assertUnprocessable();
});

it('POST /api/v1/work-orders rejects equipment from another tenant', function () {
    ['token' => $token] = apiTenantWithUser(['work-orders.write']);
    $otherTenant = Tenant::factory()->create();
    $otherEquipment = Equipment::factory()->create(['tenant_id' => $otherTenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->postJson('/api/v1/work-orders', [
        'equipment_id' => $otherEquipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p2_high',
        'title' => 'Test',
        'description' => 'Test',
    ]);

    $response->assertUnprocessable();
});

// ── PATCH /api/v1/work-orders/{id}/status ────────────────────────────────────

it('PATCH /api/v1/work-orders/{id}/status transitions from draft to planned', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'status' => 'draft',
    ]);

    $response = $this->withHeaders(apiHeaders($token))
        ->patchJson('/api/v1/work-orders/'.$workOrder->id.'/status', ['status' => 'planned']);

    $response->assertOk()
        ->assertJsonPath('data.status', 'planned');

    $this->assertDatabaseHas('work_orders', ['id' => $workOrder->id, 'status' => 'planned']);
});

it('PATCH /api/v1/work-orders/{id}/status returns 409 for invalid transition', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'status' => 'draft',
    ]);

    $response = $this->withHeaders(apiHeaders($token))
        ->patchJson('/api/v1/work-orders/'.$workOrder->id.'/status', ['status' => 'completed']);

    $response->assertStatus(409);
});

it('PATCH /api/v1/work-orders/{id}/status returns 404 for WO of another tenant', function () {
    ['token' => $token] = apiTenantWithUser(['work-orders.write']);
    $otherTenant = Tenant::factory()->create();
    $otherEquipment = Equipment::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherWO = WorkOrder::factory()->create(['tenant_id' => $otherTenant->id, 'equipment_id' => $otherEquipment->id]);

    $response = $this->withHeaders(apiHeaders($token))
        ->patchJson('/api/v1/work-orders/'.$otherWO->id.'/status', ['status' => 'planned']);

    $response->assertNotFound();
});

// ── POST /api/v1/maintenance-requests ────────────────────────────────────────

it('POST /api/v1/maintenance-requests creates a request and returns 201', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['maintenance-requests.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->postJson('/api/v1/maintenance-requests', [
        'equipment_id' => $equipment->id,
        'request_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Revisión de correa',
        'description' => 'Se observa desgaste excesivo.',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'request_number', 'status', 'equipment']])
        ->assertJsonPath('data.status', 'draft');

    expect($response->headers->has('Location'))->toBeTrue();

    $this->assertDatabaseHas('maintenance_requests', [
        'equipment_id' => $equipment->id,
        'tenant_id' => $tenant->id,
    ]);
});

it('POST /api/v1/maintenance-requests returns 403 without maintenance-requests.write ability', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['maintenance-requests.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->postJson('/api/v1/maintenance-requests', [
        'equipment_id' => $equipment->id,
        'request_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Test',
        'description' => 'Test',
    ]);

    $response->assertForbidden();
});

// ── PATCH /api/v1/maintenance-requests/{id}/status ───────────────────────────

it('PATCH /api/v1/maintenance-requests/{id}/status transitions from draft to submitted', function () {
    ['tenant' => $tenant, 'token' => $token, 'user' => $user] = apiTenantWithUser(['maintenance-requests.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $mr = MaintenanceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'status' => 'draft',
        'created_by' => $user->id,
    ]);

    $response = $this->withHeaders(apiHeaders($token))
        ->patchJson('/api/v1/maintenance-requests/'.$mr->id.'/status', ['status' => 'submitted']);

    $response->assertOk()
        ->assertJsonPath('data.status', 'submitted');
});

it('PATCH /api/v1/maintenance-requests/{id}/status returns 409 for invalid transition', function () {
    ['tenant' => $tenant, 'token' => $token, 'user' => $user] = apiTenantWithUser(['maintenance-requests.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $mr = MaintenanceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'status' => 'draft',
        'created_by' => $user->id,
    ]);

    $response = $this->withHeaders(apiHeaders($token))
        ->patchJson('/api/v1/maintenance-requests/'.$mr->id.'/status', ['status' => 'approved']);

    $response->assertStatus(409);
});

// ── POST /api/v1/inventory/transactions ──────────────────────────────────────

it('POST /api/v1/inventory/transactions creates an entry and returns 201', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['inventory.write']);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $sparePart = SparePart::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->postJson('/api/v1/inventory/transactions', [
        'type' => 'entry',
        'warehouse_id' => $warehouse->id,
        'spare_part_id' => $sparePart->id,
        'quantity' => 10,
        'unit_cost' => 25.50,
        'reference_number' => 'PO-2026-001',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'transaction_number', 'type', 'quantity', 'new_stock']]);

    $this->assertDatabaseHas('inventory_transactions', [
        'warehouse_id' => $warehouse->id,
        'spare_part_id' => $sparePart->id,
        'type' => 'entry',
    ]);
});

it('POST /api/v1/inventory/transactions creates an exit and decrements stock', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['inventory.write']);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $sparePart = SparePart::factory()->create(['tenant_id' => $tenant->id]);
    WarehouseSparePart::factory()->create([
        'tenant_id' => $tenant->id,
        'warehouse_id' => $warehouse->id,
        'spare_part_id' => $sparePart->id,
        'current_stock' => 20,
        'reserved_stock' => 0,
    ]);

    $response = $this->withHeaders(apiHeaders($token))->postJson('/api/v1/inventory/transactions', [
        'type' => 'exit',
        'warehouse_id' => $warehouse->id,
        'spare_part_id' => $sparePart->id,
        'quantity' => 5,
        'unit_cost' => 25.50,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'exit')
        ->assertJsonFragment(['new_stock' => 15]);
});

it('POST /api/v1/inventory/transactions returns 409 when exit exceeds available stock', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['inventory.write']);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $sparePart = SparePart::factory()->create(['tenant_id' => $tenant->id]);
    WarehouseSparePart::factory()->create([
        'tenant_id' => $tenant->id,
        'warehouse_id' => $warehouse->id,
        'spare_part_id' => $sparePart->id,
        'current_stock' => 3,
        'reserved_stock' => 0,
    ]);

    $response = $this->withHeaders(apiHeaders($token))->postJson('/api/v1/inventory/transactions', [
        'type' => 'exit',
        'warehouse_id' => $warehouse->id,
        'spare_part_id' => $sparePart->id,
        'quantity' => 10,
        'unit_cost' => 25.50,
    ]);

    $response->assertStatus(409);
});

it('POST /api/v1/inventory/transactions returns 403 without inventory.write ability', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['inventory.read']);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $sparePart = SparePart::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->postJson('/api/v1/inventory/transactions', [
        'type' => 'entry',
        'warehouse_id' => $warehouse->id,
        'spare_part_id' => $sparePart->id,
        'quantity' => 5,
        'unit_cost' => 10.0,
    ]);

    $response->assertForbidden();
});
