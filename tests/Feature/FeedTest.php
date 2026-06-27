<?php

use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;

// ── Helpers ───────────────────────────────────────────────────────────────────

function feedCtx(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('test-token', ['*']);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return [
        'tenant' => $tenant,
        'user' => $user,
        'token' => $tokenResult->plainTextToken,
    ];
}

function feedHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

// ── Setup ─────────────────────────────────────────────────────────────────────

beforeEach(function () {
    Cache::flush();
});

// ── Auth guard ────────────────────────────────────────────────────────────────

it('feed requiere autenticación', function () {
    $this->getJson('/api/v1/home/feed')->assertUnauthorized();
});

// ── Structure ─────────────────────────────────────────────────────────────────

it('feed retorna estructura correcta', function () {
    ['token' => $token] = feedCtx();

    $response = $this->withHeaders(feedHeaders($token))->getJson('/api/v1/home/feed?filter=all&page=1');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'items' => [],
                'page',
                'per_page',
                'total',
                'next_page',
            ],
        ]);

    expect($response->json('data.page'))->toBe(1)
        ->and($response->json('data.per_page'))->toBe(15);
});

it('feed retorna estructura de item correcta cuando hay datos', function () {
    ['tenant' => $tenant, 'token' => $token] = feedCtx();

    WorkOrder::factory()->for($tenant)->create();

    Cache::flush();

    $response = $this->withHeaders(feedHeaders($token))->getJson('/api/v1/home/feed?filter=all&page=1');

    $response->assertOk();

    $items = $response->json('data.items');
    expect($items)->not->toBeEmpty();

    $item = $items[0];
    expect($item)->toHaveKeys(['id', 'type', 'category', 'icon_type', 'title', 'occurred_at', 'occurred_at_relative']);
});

// ── Work orders ───────────────────────────────────────────────────────────────

it('feed incluye work orders creadas del tenant', function () {
    ['tenant' => $tenant, 'token' => $token] = feedCtx();

    WorkOrder::factory()->for($tenant)->create();

    Cache::flush();

    $response = $this->withHeaders(feedHeaders($token))->getJson('/api/v1/home/feed?filter=all&page=1');

    $response->assertOk();

    $types = collect($response->json('data.items'))->pluck('type');
    expect($types)->toContain('work_order_created');
});

// ── Filters ───────────────────────────────────────────────────────────────────

it('feed filtra por category work_order', function () {
    ['tenant' => $tenant, 'token' => $token] = feedCtx();

    WorkOrder::factory()->for($tenant)->create();

    Cache::flush();

    $response = $this->withHeaders(feedHeaders($token))->getJson('/api/v1/home/feed?filter=work_order&page=1');

    $response->assertOk();

    $items = $response->json('data.items');
    expect($items)->not->toBeEmpty();

    $categories = collect($items)->pluck('category')->unique()->values()->all();
    expect($categories)->toBe(['work_order']);
});

it('feed filtra por category equipment', function () {
    ['tenant' => $tenant, 'token' => $token] = feedCtx();

    Equipment::factory()->create(['tenant_id' => $tenant->id]);

    Cache::flush();

    $response = $this->withHeaders(feedHeaders($token))->getJson('/api/v1/home/feed?filter=equipment&page=1');

    $response->assertOk();

    $items = $response->json('data.items');
    expect($items)->not->toBeEmpty();

    $categories = collect($items)->pluck('category')->unique()->values()->all();
    expect($categories)->toBe(['equipment']);
});

it('feed filtra por category request', function () {
    ['tenant' => $tenant, 'token' => $token] = feedCtx();

    MaintenanceRequest::factory()->create(['tenant_id' => $tenant->id]);

    Cache::flush();

    $response = $this->withHeaders(feedHeaders($token))->getJson('/api/v1/home/feed?filter=request&page=1');

    $response->assertOk();

    $items = $response->json('data.items');
    expect($items)->not->toBeEmpty();

    $categories = collect($items)->pluck('category')->unique()->values()->all();
    expect($categories)->toBe(['request']);
});

it('feed filtra por category maintenance', function () {
    ['tenant' => $tenant, 'token' => $token] = feedCtx();

    $plan = MaintenancePlan::factory()->create(['tenant_id' => $tenant->id]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'maintenance_plan_id' => $plan->id,
        'last_completed_at' => now()->subDay(),
    ]);

    Cache::flush();

    $response = $this->withHeaders(feedHeaders($token))->getJson('/api/v1/home/feed?filter=maintenance&page=1');

    $response->assertOk();

    $items = $response->json('data.items');
    expect($items)->not->toBeEmpty();

    $categories = collect($items)->pluck('category')->unique()->values()->all();
    expect($categories)->toBe(['maintenance']);
});

// ── Tenant isolation ──────────────────────────────────────────────────────────

it('feed no muestra datos de otro tenant', function () {
    ['tenant' => $tenantA] = feedCtx();
    ['token' => $tokenB] = feedCtx();

    $woA = WorkOrder::factory()->for($tenantA)->create(['title' => 'OT solo de TenantA']);

    Cache::flush();

    $response = $this->withHeaders(feedHeaders($tokenB))->getJson('/api/v1/home/feed?filter=all&page=1');

    $response->assertOk();

    $ids = collect($response->json('data.items'))->pluck('action_id');
    expect($ids)->not->toContain($woA->id);
});

// ── Pagination ────────────────────────────────────────────────────────────────

it('feed pagina correctamente', function () {
    ['tenant' => $tenant, 'token' => $token] = feedCtx();

    WorkOrder::factory()->for($tenant)->count(16)->create();

    Cache::flush();

    $response = $this->withHeaders(feedHeaders($token))->getJson('/api/v1/home/feed?filter=work_order&page=1');

    $response->assertOk();

    expect($response->json('data.items'))->toHaveCount(15)
        ->and($response->json('data.next_page'))->not->toBeNull();
});
