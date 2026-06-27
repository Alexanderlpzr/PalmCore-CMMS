<?php

use App\Models\InstitutionalContent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

// ── Helpers ───────────────────────────────────────────────────────────────────

function contentCtx(): array
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

function contentHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

// ── Setup ─────────────────────────────────────────────────────────────────────

beforeEach(fn () => Cache::flush());

// ── GET /api/v1/home/content ──────────────────────────────────────────────────

it('content retorna items globales para cualquier tenant', function () {
    $content = InstitutionalContent::factory()->create([
        'is_global' => true,
        'is_active' => true,
        'title' => 'Contenido Global',
        'starts_at' => null,
        'ends_at' => null,
    ]);

    ['token' => $tokenA] = contentCtx();
    ['token' => $tokenB] = contentCtx();

    $responseA = $this->withHeaders(contentHeaders($tokenA))->getJson('/api/v1/home/content');
    $responseB = $this->withHeaders(contentHeaders($tokenB))->getJson('/api/v1/home/content');

    $responseA->assertOk();
    $responseB->assertOk();

    $idsA = collect($responseA->json('data'))->pluck('id');
    $idsB = collect($responseB->json('data'))->pluck('id');

    expect($idsA)->toContain($content->id)
        ->and($idsB)->toContain($content->id);
});

it('content retorna items especificos al tenant asignado', function () {
    ['tenant' => $tenantA, 'token' => $tokenA] = contentCtx();

    $content = InstitutionalContent::factory()->create([
        'is_global' => false,
        'is_active' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    $content->tenants()->attach($tenantA->id);

    $response = $this->withHeaders(contentHeaders($tokenA))->getJson('/api/v1/home/content');

    $response->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->toContain($content->id);
});

it('contenido no-global no es visible para tenant no asignado', function () {
    // Use Tenant B's context; content is attached to a separate tenant that B doesn't own.
    ['token' => $tokenB] = contentCtx();
    $otherTenant = Tenant::factory()->create();

    $content = InstitutionalContent::factory()->create([
        'is_global' => false,
        'is_active' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    $content->tenants()->attach($otherTenant->id);

    $response = $this->withHeaders(contentHeaders($tokenB))->getJson('/api/v1/home/content');

    $response->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->not->toContain($content->id);
});

it('content excluye items inactivos', function () {
    ['token' => $token] = contentCtx();

    InstitutionalContent::factory()->inactive()->create(['is_global' => true]);

    $response = $this->withHeaders(contentHeaders($token))->getJson('/api/v1/home/content');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('content excluye items expirados', function () {
    ['token' => $token] = contentCtx();

    InstitutionalContent::factory()->expired()->create(['is_global' => true]);

    $response = $this->withHeaders(contentHeaders($token))->getJson('/api/v1/home/content');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('content excluye items con starts_at en el futuro', function () {
    ['token' => $token] = contentCtx();

    InstitutionalContent::factory()->create([
        'is_global' => true,
        'is_active' => true,
        'starts_at' => now()->addDays(7),
        'ends_at' => null,
    ]);

    $response = $this->withHeaders(contentHeaders($token))->getJson('/api/v1/home/content');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('content retorna items ordenados por display_order', function () {
    ['token' => $token] = contentCtx();

    $c30 = InstitutionalContent::factory()->create(['is_global' => true, 'is_active' => true, 'display_order' => 30, 'starts_at' => null, 'ends_at' => null]);
    $c10 = InstitutionalContent::factory()->create(['is_global' => true, 'is_active' => true, 'display_order' => 10, 'starts_at' => null, 'ends_at' => null]);
    $c20 = InstitutionalContent::factory()->create(['is_global' => true, 'is_active' => true, 'display_order' => 20, 'starts_at' => null, 'ends_at' => null]);

    $response = $this->withHeaders(contentHeaders($token))->getJson('/api/v1/home/content');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    $expected = [$c10->id, $c20->id, $c30->id];

    // Extract just these three items in their returned order
    $filteredIds = array_values(array_filter($ids, fn ($id) => in_array($id, $expected)));

    expect($filteredIds)->toBe($expected);
});

it('content requiere autenticacion', function () {
    $this->getJson('/api/v1/home/content')->assertUnauthorized();
});
