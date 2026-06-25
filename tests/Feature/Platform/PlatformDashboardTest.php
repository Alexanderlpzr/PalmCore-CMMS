<?php

use App\Models\Alert;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;

/**
 * Sprint ADMIN-3 — Super Admin platform dashboard (cross-tenant aggregates).
 */
beforeEach(function () {
    // Platform metrics are cached globally; isolate each test.
    Cache::flush();
});

function platformToken(bool $superAdmin = true): array
{
    $user = User::factory()->create(['is_super_admin' => $superAdmin, 'is_active' => true]);
    $token = $user->createToken('test', ['*'])->plainTextToken;

    return [$user, ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']];
}

function makeCriticalAlert(string $tenantId, string $severity = 'critical', string $status = 'open'): void
{
    (new Alert)->forceFill([
        'tenant_id' => $tenantId,
        'severity' => $severity,
        'category' => 'system',
        'title' => 'Test alert',
        'status' => $status,
        'created_at' => now(),
    ])->save();
}

// ── Access control (Security) ─────────────────────────────────────────────────

it('allows a super admin to load the global summary', function () {
    [, $headers] = platformToken();
    Tenant::factory()->create();
    Tenant::factory()->create();

    $response = $this->getJson(route('api.v1.platform.summary'), $headers);

    $response->assertOk()->assertJsonStructure([
        'tenants' => ['total', 'active'],
        'users' => ['total', 'active'],
        'equipment' => ['total'],
        'open_work_orders',
        'preventive_plans',
        'critical_alerts',
        'avg_availability',
        'global_cost_month',
    ]);

    expect($response->json('tenants.total'))->toBe(2);
});

it('rejects a non-super-admin user with 403', function () {
    [, $headers] = platformToken(superAdmin: false);

    $this->getJson(route('api.v1.platform.summary'), $headers)->assertForbidden();
    $this->getJson(route('api.v1.platform.analytics'), $headers)->assertForbidden();
});

it('rejects unauthenticated requests with 401', function () {
    $this->getJson(route('api.v1.platform.summary'))->assertUnauthorized();
    $this->getJson(route('api.v1.platform.analytics'))->assertUnauthorized();
});

// ── Cross-tenant aggregation (Backend / Analytics) ────────────────────────────

it('aggregates open work orders and critical alerts across all tenants', function () {
    [, $headers] = platformToken();
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    WorkOrder::factory()->create(['tenant_id' => $a->id, 'status' => 'planned']);
    WorkOrder::factory()->create(['tenant_id' => $b->id, 'status' => 'in_progress']);

    makeCriticalAlert($a->id, severity: 'critical', status: 'open');
    makeCriticalAlert($b->id, severity: 'warning', status: 'open'); // not critical
    makeCriticalAlert($b->id, severity: 'critical', status: 'resolved'); // not open

    $response = $this->getJson(route('api.v1.platform.summary'), $headers)->assertOk();

    expect($response->json('open_work_orders'))->toBe(2)
        ->and($response->json('critical_alerts'))->toBe(1);
});

it('ranks tenants by equipment count', function () {
    [, $headers] = platformToken();
    $alpha = Tenant::factory()->create(['name' => 'Alpha']);
    $beta = Tenant::factory()->create(['name' => 'Beta']);

    Equipment::factory()->count(5)->create(['tenant_id' => $alpha->id]);
    Equipment::factory()->count(1)->create(['tenant_id' => $beta->id]);

    $response = $this->getJson(route('api.v1.platform.analytics'), $headers)->assertOk();

    $response->assertJsonStructure([
        'top_by_equipment' => [['tenant_id', 'name', 'count']],
        'top_by_work_orders',
        'top_by_alerts',
        'storage' => ['total_bytes', 'by_tenant'],
        'subscriptions' => ['active', 'by_plan'],
        'expiring_soon',
    ]);

    expect($response->json('top_by_equipment.0.name'))->toBe('Alpha')
        ->and($response->json('top_by_equipment.0.count'))->toBe(5);
});

it('lists only tenants expiring within the next 30 days', function () {
    [, $headers] = platformToken();
    Tenant::factory()->create(['name' => 'SoonCo', 'subscription_expires_at' => now()->addDays(10)]);
    Tenant::factory()->create(['name' => 'FarCo', 'subscription_expires_at' => now()->addDays(90)]);

    $response = $this->getJson(route('api.v1.platform.analytics'), $headers)->assertOk();

    $names = collect($response->json('expiring_soon'))->pluck('name');
    expect($names)->toContain('SoonCo')
        ->and($names)->not->toContain('FarCo');
});

it('counts active subscriptions', function () {
    [, $headers] = platformToken();
    Tenant::factory()->create(['is_active' => true, 'subscription_expires_at' => now()->addYear()]);
    Tenant::factory()->create(['is_active' => true, 'subscription_expires_at' => null]);
    Tenant::factory()->create(['is_active' => false, 'subscription_expires_at' => now()->addYear()]);

    $response = $this->getJson(route('api.v1.platform.analytics'), $headers)->assertOk();

    // Two active (one dated in the future, one perpetual); the inactive one excluded.
    expect($response->json('subscriptions.active'))->toBe(2);
});
