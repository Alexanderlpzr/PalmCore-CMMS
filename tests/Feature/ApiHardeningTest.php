<?php

use App\Models\ApiRequestLog;
use App\Models\Equipment;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Str;

// ── Helpers ───────────────────────────────────────────────────────────────────

function hardeningTenantWithUser(array $abilities = ['*']): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('hardening-token', $abilities);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return [
        'tenant' => $tenant,
        'user' => $user,
        'token' => $tokenResult->plainTextToken,
    ];
}

function hardeningHeaders(string $token, ?string $idempotencyKey = null): array
{
    $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];

    if ($idempotencyKey !== null) {
        $headers['Idempotency-Key'] = $idempotencyKey;
    }

    return $headers;
}

// ── Idempotency-Key middleware ────────────────────────────────────────────────

it('POST with Idempotency-Key creates a work order and persists the key', function () {
    ['tenant' => $tenant, 'token' => $token] = hardeningTenantWithUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $key = Str::uuid()->toString();

    $payload = [
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p2_high',
        'title' => 'Bomba fallando',
        'description' => 'Pérdida de presión.',
    ];

    $this->withHeaders(hardeningHeaders($token, $key))->postJson('/api/v1/work-orders', $payload)
        ->assertCreated();

    $this->assertDatabaseHas('idempotency_keys', [
        'tenant_id' => $tenant->id,
        'idempotency_key' => $key,
    ]);
});

it('POST with same Idempotency-Key returns cached response without re-executing', function () {
    ['tenant' => $tenant, 'token' => $token] = hardeningTenantWithUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $key = Str::uuid()->toString();

    $payload = [
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p2_high',
        'title' => 'Bomba fallando',
        'description' => 'Pérdida de presión.',
    ];

    $first = $this->withHeaders(hardeningHeaders($token, $key))->postJson('/api/v1/work-orders', $payload);
    $second = $this->withHeaders(hardeningHeaders($token, $key))->postJson('/api/v1/work-orders', $payload);

    $first->assertCreated();
    $second->assertCreated();
    $second->assertHeader('Idempotency-Replayed', 'true');

    $this->assertDatabaseCount('work_orders', 1);
});

it('POST with same Idempotency-Key but different body returns 422', function () {
    ['tenant' => $tenant, 'token' => $token] = hardeningTenantWithUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $key = Str::uuid()->toString();

    $this->withHeaders(hardeningHeaders($token, $key))->postJson('/api/v1/work-orders', [
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p2_high',
        'title' => 'Primera solicitud',
        'description' => 'Desc A.',
    ])->assertCreated();

    $this->withHeaders(hardeningHeaders($token, $key))->postJson('/api/v1/work-orders', [
        'equipment_id' => $equipment->id,
        'work_order_type' => 'preventive',
        'priority' => 'p3_medium',
        'title' => 'Segunda solicitud diferente',
        'description' => 'Desc B.',
    ])->assertUnprocessable();
});

it('POST with invalid Idempotency-Key format returns 422', function () {
    ['token' => $token] = hardeningTenantWithUser(['work-orders.write']);

    $this->withHeaders(hardeningHeaders($token, 'not-a-uuid'))->postJson('/api/v1/work-orders', [])
        ->assertUnprocessable();
});

it('POST with a UUID v1 Idempotency-Key returns 422 — only v4 accepted', function () {
    ['token' => $token] = hardeningTenantWithUser(['work-orders.write']);

    // UUID v1: version nibble is '1', not '4'
    $uuidV1 = '550e8400-e29b-11d4-a716-446655440000';

    $this->withHeaders(hardeningHeaders($token, $uuidV1))->postJson('/api/v1/work-orders', [])
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Idempotency-Key must be a valid UUID v4.']);
});

it('POST with a UUID v4 Idempotency-Key is accepted', function () {
    ['tenant' => $tenant, 'token' => $token] = hardeningTenantWithUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $uuidV4 = '550e8400-e29b-4716-a400-446655440000';

    $this->withHeaders(hardeningHeaders($token, $uuidV4))->postJson('/api/v1/work-orders', [
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p2_high',
        'title' => 'UUID v4 test',
        'description' => 'Validating v4 acceptance.',
    ])->assertCreated();
});

it('POST without Idempotency-Key works normally', function () {
    ['tenant' => $tenant, 'token' => $token] = hardeningTenantWithUser(['work-orders.write']);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $this->withHeaders(hardeningHeaders($token))->postJson('/api/v1/work-orders', [
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p2_high',
        'title' => 'Sin idempotency key',
        'description' => 'Test normal.',
    ])->assertCreated();
});

it('Idempotency-Key is tenant-scoped — different tenants can reuse the same key value', function () {
    ['tenant' => $tenantA, 'token' => $tokenA] = hardeningTenantWithUser(['work-orders.write']);
    ['tenant' => $tenantB, 'token' => $tokenB] = hardeningTenantWithUser(['work-orders.write']);
    $equipA = Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);
    $sharedKey = Str::uuid()->toString();

    $payload = fn (string $equipId) => [
        'equipment_id' => $equipId,
        'work_order_type' => 'corrective',
        'priority' => 'p2_high',
        'title' => 'Test',
        'description' => 'Test.',
    ];

    $this->withHeaders(hardeningHeaders($tokenA, $sharedKey))->postJson('/api/v1/work-orders', $payload($equipA->id))->assertCreated();
    app('auth')->forgetGuards();
    $this->withHeaders(hardeningHeaders($tokenB, $sharedKey))->postJson('/api/v1/work-orders', $payload($equipB->id))->assertCreated();

    $this->assertDatabaseCount('work_orders', 2);
    $this->assertDatabaseCount('idempotency_keys', 2);
});

// ── Audit logging ─────────────────────────────────────────────────────────────

it('API requests are logged in api_request_logs', function () {
    ['tenant' => $tenant, 'token' => $token] = hardeningTenantWithUser(['equipment.read']);
    Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $this->withHeaders(hardeningHeaders($token))->getJson('/api/v1/equipment');

    $this->assertDatabaseCount('api_request_logs', 1);
});

it('api_request_logs captures correct metadata', function () {
    ['tenant' => $tenant, 'token' => $token] = hardeningTenantWithUser(['equipment.read']);

    $this->withHeaders(hardeningHeaders($token))->getJson('/api/v1/equipment');

    $log = ApiRequestLog::first();

    expect($log)->not->toBeNull()
        ->and($log->tenant_id)->toBe($tenant->id)
        ->and($log->method)->toBe('GET')
        ->and($log->path)->toBe('/api/v1/equipment')
        ->and($log->status_code)->toBe(200)
        ->and($log->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('api_request_logs records 4xx responses', function () {
    // Use a valid token without a tenant scope → ResolveApiTenant returns 403 (explicit response, not exception)
    $user = User::factory()->create(['is_active' => true]);
    $tokenResult = $user->createToken('no-tenant-token', ['*']);
    // Intentionally do NOT set tenant_id on the token

    $this->withHeaders([
        'Authorization' => 'Bearer '.$tokenResult->plainTextToken,
        'Accept' => 'application/json',
    ])->getJson('/api/v1/equipment')->assertForbidden();

    $this->assertDatabaseCount('api_request_logs', 1);
    expect(ApiRequestLog::first()->status_code)->toBe(403);
});

// ── InventoryService race condition fix ───────────────────────────────────────

it('inventory entry creates WarehouseSparePart row when none exists', function () {
    ['tenant' => $tenant, 'token' => $token] = hardeningTenantWithUser(['inventory.write']);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $sparePart = SparePart::factory()->create(['tenant_id' => $tenant->id]);

    $this->withHeaders(hardeningHeaders($token))->postJson('/api/v1/inventory/transactions', [
        'type' => 'entry',
        'warehouse_id' => $warehouse->id,
        'spare_part_id' => $sparePart->id,
        'quantity' => 5,
        'unit_cost' => 10,
    ])->assertCreated();

    $this->assertDatabaseHas('warehouse_spare_parts', [
        'warehouse_id' => $warehouse->id,
        'spare_part_id' => $sparePart->id,
    ]);
});
