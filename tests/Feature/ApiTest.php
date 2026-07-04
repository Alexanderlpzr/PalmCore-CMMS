<?php

use App\Domain\Assets\Enums\EquipmentPriority;
use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\EquipmentKpi;
use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanTask;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\PersonalAccessToken;
use App\Models\Plant;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;

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

it('GET /api/v1/equipment supports comma-separated status filter', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser();

    Equipment::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
    Equipment::factory()->create(['tenant_id' => $tenant->id, 'status' => 'inactive']);
    Equipment::factory()->create(['tenant_id' => $tenant->id, 'status' => 'under_maintenance']);

    $response = $this->withHeaders(apiHeaders($token))
        ->getJson('/api/v1/equipment?status=inactive,under_maintenance');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('GET /api/v1/equipment search matches code, name and serial (case-insensitive)', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser();

    $match = Equipment::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bomba Hidráulica', 'serial_number' => 'SN-001']);
    Equipment::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Motor Eléctrico', 'serial_number' => 'SN-999']);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment?search=bomba');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

it('GET /api/v1/equipment search finds a record that exists (no false negatives)', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser();

    Equipment::factory()->count(60)->create(['tenant_id' => $tenant->id]);
    $needle = Equipment::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Compresor Atlas Copco XR-77']);

    // Default per_page is 25 — the needle would be invisible with client-side filtering,
    // but server-side search must surface it regardless of pagination position.
    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment?search=XR-77');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $needle->id);
});

it('GET /api/v1/equipment supports sort by name with direction', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser();

    Equipment::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Zeta']);
    Equipment::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alfa']);

    $asc = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment?sort=name&direction=asc');
    $asc->assertOk()->assertJsonPath('data.0.name', 'Alfa');

    $desc = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment?sort=name&direction=desc');
    $desc->assertOk()->assertJsonPath('data.0.name', 'Zeta');
});

it('GET /api/v1/equipment ignores a non-whitelisted sort column (falls back to default)', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser();
    Equipment::factory()->count(2)->create(['tenant_id' => $tenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment?sort=password&direction=desc');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('GET /api/v1/equipment marks has_overdue_preventives on list items', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser();

    $withOverdue = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    MaintenanceSchedule::factory()->overdue()->create([
        'tenant_id' => $tenant->id,
        'maintenance_plan_id' => MaintenancePlan::factory()->create([
            'tenant_id' => $tenant->id,
            'equipment_id' => $withOverdue->id,
            'is_active' => true,
        ])->id,
    ]);

    $withoutOverdue = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment');

    $response->assertOk();
    $byId = collect($response->json('data'))->keyBy('id');
    expect($byId[$withOverdue->id]['has_overdue_preventives'])->toBeTrue();
    expect($byId[$withoutOverdue->id]['has_overdue_preventives'])->toBeFalse();
});

it('GET /api/v1/equipment includes kpi data on list items when available', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser();

    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    EquipmentKpi::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'availability_percentage' => 92.5,
    ]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment');

    $response->assertOk();
    $item = collect($response->json('data'))->firstWhere('id', $equipment->id);
    expect((float) $item['kpi']['availability_percentage'])->toBe(92.5);
});

it('GET /api/v1/inventory/spare-parts search matches code, name and description', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['inventory.read']);

    $match = SparePart::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Rodamiento SKF 6205']);
    SparePart::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Sello mecánico']);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/inventory/spare-parts?search=skf');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
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

it('GET /api/v1/work-orders search matches work_order_number, title and description', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $match = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'title' => 'Cambio de correa transportadora',
    ]);
    WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'title' => 'Lubricación general',
    ]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/work-orders?search=correa');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

it('GET /api/v1/work-orders supports comma-separated status filter', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'status' => 'planned']);
    WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'status' => 'in_progress']);
    WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'status' => 'closed']);

    $response = $this->withHeaders(apiHeaders($token))
        ->getJson('/api/v1/work-orders?status=planned,in_progress');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('GET /api/v1/maintenance-requests supports comma-separated status filter', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['maintenance-requests.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    MaintenanceRequest::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'status' => 'submitted']);
    MaintenanceRequest::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'status' => 'under_review']);
    MaintenanceRequest::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'status' => 'approved']);

    $response = $this->withHeaders(apiHeaders($token))
        ->getJson('/api/v1/maintenance-requests?status=submitted,under_review');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
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
    ['tenant' => $tenant, 'user' => $user, 'token' => $token] = apiTenantWithUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'status' => 'draft',
    ]);

    app(WorkOrderService::class)->assignTechnician($workOrder, $user, TechnicianRole::Technician);

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

// ── PATCH /api/v1/work-orders/{id}/status — Completion Experience fields ────

it('PATCH .../status persists work_performed when completing the work order', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->inProgress()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
    ]);

    $response = $this->withHeaders(apiHeaders($token))
        ->patchJson('/api/v1/work-orders/'.$workOrder->id.'/status', [
            'status' => 'completed',
            'work_performed' => 'Se reemplazó el rodamiento y se realineó el eje.',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.work_performed', 'Se reemplazó el rodamiento y se realineó el eje.');

    $this->assertDatabaseHas('work_orders', [
        'id' => $workOrder->id,
        'work_performed' => 'Se reemplazó el rodamiento y se realineó el eje.',
    ]);
});

it('PATCH .../status without work_performed does not blank out an existing value', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->completed()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_performed' => 'Diagnóstico inicial registrado.',
    ]);

    $response = $this->withHeaders(apiHeaders($token))
        ->patchJson('/api/v1/work-orders/'.$workOrder->id.'/status', ['status' => 'verified']);

    $response->assertOk();
    $this->assertDatabaseHas('work_orders', [
        'id' => $workOrder->id,
        'work_performed' => 'Diagnóstico inicial registrado.',
    ]);
});

// ── GET /api/v1/work-orders/{id} — Evidence Zone data ────────────────────────

it('GET /api/v1/work-orders/{id} includes attachments, signatures, checklist and completion in mission', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $plan = MaintenancePlan::factory()->create(['tenant_id' => $tenant->id]);
    MaintenancePlanTask::factory()->create(['tenant_id' => $tenant->id, 'maintenance_plan_id' => $plan->id]);
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'maintenance_plan_id' => $plan->id,
    ]);
    WorkOrderAttachment::factory()->create(['tenant_id' => $tenant->id, 'work_order_id' => $workOrder->id]);

    $response = $this->withHeaders(apiHeaders($token))
        ->getJson('/api/v1/work-orders/'.$workOrder->id);

    $response->assertOk()
        ->assertJsonCount(1, 'data.attachments')
        ->assertJsonCount(1, 'data.mission.checklist')
        ->assertJsonStructure(['data' => ['mission' => ['completion' => ['readiness', 'summary']]]]);
});

// ── GET /api/v1/work-orders/{id} — Mission Workspace data ───────────────────

it('GET /api/v1/work-orders/{id} includes mission data for a single work order', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'equipment_stopped' => true,
        'status' => 'draft',
    ]);

    $response = $this->withHeaders(apiHeaders($token))
        ->getJson('/api/v1/work-orders/'.$workOrder->id);

    $response->assertOk()
        ->assertJsonPath('data.mission.expected_outcome', 'Restablecer la operación del equipo')
        ->assertJsonPath('data.mission.progress.percentage', 0)
        ->assertJsonPath('data.mission.progress.current_status', 'draft')
        ->assertJsonStructure(['data' => ['mission' => ['expected_outcome', 'progress', 'previous_intervention', 'origin']]]);
});

it('GET /api/v1/work-orders (list) does not compute mission data — avoids N+1 per row', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/work-orders');

    $response->assertOk()
        ->assertJsonPath('data.0.mission', null);
});

it('GET /api/v1/work-orders/{id} reports the originating maintenance request in mission.origin', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.read']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $request = MaintenanceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'title' => 'Vibración anormal en el eje',
    ]);
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'maintenance_request_id' => $request->id,
    ]);

    $response = $this->withHeaders(apiHeaders($token))
        ->getJson('/api/v1/work-orders/'.$workOrder->id);

    $response->assertOk()
        ->assertJsonPath('data.mission.origin.type', 'request')
        ->assertJsonPath('data.mission.origin.description', 'Vibración anormal en el eje');
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

// ── RC1 — DT-01: EquipmentPriority enum values aligned with shared contract ──

it('GET /api/v1/equipment returns priority with p1_critical format after enum alignment', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['equipment.read']);

    Equipment::factory()->create([
        'tenant_id' => $tenant->id,
        'priority' => EquipmentPriority::P1,
    ]);

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment');

    $response->assertOk();

    $priority = $response->json('data.0.priority');
    expect($priority)->toBe('p1_critical');
});

it('GET /api/v1/equipment returns all four priority values in the aligned format', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['equipment.read']);

    foreach (EquipmentPriority::cases() as $case) {
        Equipment::factory()->create(['tenant_id' => $tenant->id, 'priority' => $case]);
    }

    $response = $this->withHeaders(apiHeaders($token))->getJson('/api/v1/equipment?per_page=100');

    $response->assertOk();

    $priorities = collect($response->json('data'))->pluck('priority')->unique()->sort()->values()->toArray();
    expect($priorities)->toContain('p1_critical', 'p2_high', 'p3_medium', 'p4_low');
});

// ── RC1 — DT-04: WO list preserves equipment context ─────────────────────────

it('GET /api/v1/work-orders?equipment_id=X returns only WOs for that equipment', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.read']);

    $equipA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    WorkOrder::factory()->count(2)->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipA->id]);
    WorkOrder::factory()->count(3)->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipB->id]);

    $response = $this->withHeaders(apiHeaders($token))
        ->getJson('/api/v1/work-orders?equipment_id='.$equipA->id);

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('equipment.id')->unique()->values()->toArray();
    expect($ids)->toBe([$equipA->id])
        ->and(count($response->json('data')))->toBe(2);
});

it('GET /api/v1/work-orders?equipment_id from another tenant returns empty', function () {
    ['token' => $tokenA] = apiTenantWithUser(['work-orders.read']);

    $tenantB = Tenant::factory()->create();
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);
    WorkOrder::factory()->create(['tenant_id' => $tenantB->id, 'equipment_id' => $equipB->id]);

    $response = $this->withHeaders(apiHeaders($tokenA))
        ->getJson('/api/v1/work-orders?equipment_id='.$equipB->id);

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

// ── RC1 — Security: QuickReportPanel (POST /api/v1/maintenance-requests) ─────

it('POST /api/v1/maintenance-requests rejects equipment from another tenant', function () {
    ['token' => $token] = apiTenantWithUser(['maintenance-requests.write']);
    $otherTenant = Tenant::factory()->create();
    $otherEquipment = Equipment::factory()->create(['tenant_id' => $otherTenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->postJson('/api/v1/maintenance-requests', [
        'equipment_id' => $otherEquipment->id,
        'request_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Test cross-tenant',
        'description' => 'Tenant isolation test.',
    ]);

    $response->assertUnprocessable();
});

it('POST /api/v1/maintenance-requests returns 422 for invalid equipment_id', function () {
    ['token' => $token] = apiTenantWithUser(['maintenance-requests.write']);

    $response = $this->withHeaders(apiHeaders($token))->postJson('/api/v1/maintenance-requests', [
        'equipment_id' => 'non-existent-uuid',
        'request_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Test',
        'description' => 'Test',
    ]);

    $response->assertUnprocessable();
});

it('POST /api/v1/maintenance-requests returns 422 for invalid priority value', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['maintenance-requests.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->postJson('/api/v1/maintenance-requests', [
        'equipment_id' => $equipment->id,
        'request_type' => 'corrective',
        'priority' => 'p3',
        'title' => 'Test',
        'description' => 'Test',
    ]);

    $response->assertUnprocessable();
});

it('POST /api/v1/work-orders returns 422 for invalid priority value', function () {
    ['tenant' => $tenant, 'token' => $token] = apiTenantWithUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->withHeaders(apiHeaders($token))->postJson('/api/v1/work-orders', [
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p2',
        'title' => 'Test',
        'description' => 'Test',
    ]);

    $response->assertUnprocessable();
});
