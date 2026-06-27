<?php

use App\Models\Announcement;
use App\Models\CarouselSlide;
use App\Models\MaintenanceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;

// ── Helpers ───────────────────────────────────────────────────────────────────

function homeCtx(): array
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

function homeHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

// ── GET /api/v1/home/carousel ─────────────────────────────────────────────────

it('carousel retorna slides activos del tenant', function () {
    ['tenant' => $tenant, 'token' => $token] = homeCtx();

    CarouselSlide::factory()->for($tenant)->create(['title' => 'Slide Activo', 'is_active' => true, 'starts_at' => null, 'ends_at' => null]);

    Cache::flush();

    $response = $this->withHeaders(homeHeaders($token))->getJson('/api/v1/home/carousel');

    $response->assertOk()
        ->assertJsonPath('data.0.title', 'Slide Activo');
});

it('carousel excluye slides soft-deleted', function () {
    ['tenant' => $tenant, 'token' => $token] = homeCtx();

    $slide = CarouselSlide::factory()->for($tenant)->create(['is_active' => true]);
    $slide->delete();

    Cache::flush();

    $response = $this->withHeaders(homeHeaders($token))->getJson('/api/v1/home/carousel');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('carousel excluye slides expirados', function () {
    ['tenant' => $tenant, 'token' => $token] = homeCtx();

    CarouselSlide::factory()->for($tenant)->expired()->create(['is_active' => true]);

    Cache::flush();

    $response = $this->withHeaders(homeHeaders($token))->getJson('/api/v1/home/carousel');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('carousel excluye slides inactivos', function () {
    ['tenant' => $tenant, 'token' => $token] = homeCtx();

    CarouselSlide::factory()->for($tenant)->inactive()->create();

    Cache::flush();

    $response = $this->withHeaders(homeHeaders($token))->getJson('/api/v1/home/carousel');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

// ── GET /api/v1/home/announcements ───────────────────────────────────────────

it('announcements retorna comunicados publicados', function () {
    ['tenant' => $tenant, 'token' => $token] = homeCtx();

    Announcement::factory()->for($tenant)->create(['title' => 'Comunicado Público', 'is_active' => true]);

    Cache::flush();

    $response = $this->withHeaders(homeHeaders($token))->getJson('/api/v1/home/announcements');

    $response->assertOk()
        ->assertJsonPath('data.0.title', 'Comunicado Público');
});

it('announcements excluye comunicados inactivos', function () {
    ['tenant' => $tenant, 'token' => $token] = homeCtx();

    Announcement::factory()->for($tenant)->inactive()->create();

    Cache::flush();

    $response = $this->withHeaders(homeHeaders($token))->getJson('/api/v1/home/announcements');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('announcements excluye comunicados expirados', function () {
    ['tenant' => $tenant, 'token' => $token] = homeCtx();

    Announcement::factory()->for($tenant)->expired()->create(['is_active' => true]);

    Cache::flush();

    $response = $this->withHeaders(homeHeaders($token))->getJson('/api/v1/home/announcements');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('announcements excluye comunicados futuros', function () {
    ['tenant' => $tenant, 'token' => $token] = homeCtx();

    Announcement::factory()->for($tenant)->future()->create(['is_active' => true]);

    Cache::flush();

    $response = $this->withHeaders(homeHeaders($token))->getJson('/api/v1/home/announcements');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('announcements pinned aparecen primero', function () {
    ['tenant' => $tenant, 'token' => $token] = homeCtx();

    Announcement::factory()->for($tenant)->create(['title' => 'Normal', 'sort_order' => 0]);
    Announcement::factory()->for($tenant)->pinned()->create(['title' => 'Fijado', 'sort_order' => 99]);

    Cache::flush();

    $response = $this->withHeaders(homeHeaders($token))->getJson('/api/v1/home/announcements');

    $response->assertOk();
    expect($response->json('data.0.title'))->toBe('Fijado');
});

// ── GET /api/v1/home/notices ─────────────────────────────────────────────────

it('notices returns correct structure', function () {
    ['token' => $token] = homeCtx();

    Cache::flush();

    $response = $this->withHeaders(homeHeaders($token))->getJson('/api/v1/home/notices');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['type', 'count', 'label', 'route', 'color', 'visible'],
            ],
        ]);

    expect($response->json('data'))->toHaveCount(4);
});

it('notices visible=true when overdue WO exists', function () {
    ['tenant' => $tenant, 'token' => $token] = homeCtx();

    WorkOrder::factory()->for($tenant)->create([
        'status' => 'in_progress',
        'planned_end_at' => now()->subDay(),
    ]);

    Cache::flush();

    $response = $this->withHeaders(homeHeaders($token))->getJson('/api/v1/home/notices');

    $response->assertOk();

    $overdue = collect($response->json('data'))->firstWhere('type', 'overdue_wo');
    expect($overdue['count'])->toBeGreaterThanOrEqual(1)
        ->and($overdue['visible'])->toBeTrue();
});

it('notices visible=true when pending maintenance requests exist', function () {
    ['tenant' => $tenant, 'token' => $token] = homeCtx();

    MaintenanceRequest::factory()->for($tenant)->create(['status' => 'submitted']);

    Cache::flush();

    $response = $this->withHeaders(homeHeaders($token))->getJson('/api/v1/home/notices');

    $response->assertOk();

    $pending = collect($response->json('data'))->firstWhere('type', 'pending_requests');
    expect($pending['count'])->toBeGreaterThanOrEqual(1)
        ->and($pending['visible'])->toBeTrue();
});

// ── GET /api/v1/home/activity ─────────────────────────────────────────────────

it('activity returns correct structure', function () {
    ['token' => $token] = homeCtx();

    Cache::flush();

    $response = $this->withHeaders(homeHeaders($token))->getJson('/api/v1/home/activity');

    $response->assertOk()
        ->assertJsonStructure(['data']);
});

it('activity includes work orders from tenant', function () {
    ['tenant' => $tenant, 'token' => $token] = homeCtx();

    WorkOrder::factory()->for($tenant)->create(['title' => 'OT de prueba']);

    Cache::flush();

    $response = $this->withHeaders(homeHeaders($token))->getJson('/api/v1/home/activity');

    $response->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('OT de prueba');
});

// ── Auth guard ────────────────────────────────────────────────────────────────

it('carousel requiere autenticación', function () {
    $this->getJson('/api/v1/home/carousel')->assertUnauthorized();
});

it('announcements requiere autenticación', function () {
    $this->getJson('/api/v1/home/announcements')->assertUnauthorized();
});
