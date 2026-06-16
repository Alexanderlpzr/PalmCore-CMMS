<?php

use App\Models\Equipment;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

function searchUser(array $abilities): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('palette', $abilities);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return ['tenant' => $tenant, 'user' => $user, 'token' => $tokenResult->plainTextToken];
}

function searchHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

it('GET /api/v1/search groups results across resources', function () {
    ['tenant' => $tenant, 'token' => $token] = searchUser(['*']);

    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bomba Centrífuga Norte']);
    WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'title' => 'Revisión bomba']);

    $response = $this->withHeaders(searchHeaders($token))->getJson('/api/v1/search?q=bomba');

    $response->assertOk()
        ->assertJsonPath('query', 'bomba')
        ->assertJsonStructure(['groups' => [['type', 'label', 'items' => [['id', 'title', 'subtitle']]]]]);

    $types = collect($response->json('groups'))->pluck('type');
    expect($types)->toContain('equipment')->toContain('work_orders');
});

it('GET /api/v1/search is case-insensitive and matches by code', function () {
    ['tenant' => $tenant, 'token' => $token] = searchUser(['equipment.read']);
    $needle = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => 'EQ-XR77', 'name' => 'Compresor']);

    $response = $this->withHeaders(searchHeaders($token))->getJson('/api/v1/search?q=xr77');

    $response->assertOk()
        ->assertJsonPath('groups.0.type', 'equipment')
        ->assertJsonPath('groups.0.items.0.id', $needle->id);
});

it('GET /api/v1/search enforces tenant isolation', function () {
    ['token' => $tokenA] = searchUser(['*']);
    $tenantB = Tenant::factory()->create();
    Equipment::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Motor Ajeno']);

    $response = $this->withHeaders(searchHeaders($tokenA))->getJson('/api/v1/search?q=ajeno');

    $response->assertOk()
        ->assertExactJson(['query' => 'ajeno', 'groups' => []]);
});

it('GET /api/v1/search only returns groups the token can read', function () {
    ['tenant' => $tenant, 'token' => $token] = searchUser(['equipment.read']);

    Equipment::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Tablero Alfa']);
    SparePart::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Tablero repuesto']);

    $response = $this->withHeaders(searchHeaders($token))->getJson('/api/v1/search?q=tablero');

    $types = collect($response->json('groups'))->pluck('type');
    expect($types)->toContain('equipment')->not->toContain('spare_parts');
});

it('GET /api/v1/search returns empty groups below the minimum query length', function () {
    ['token' => $token] = searchUser(['*']);

    $response = $this->withHeaders(searchHeaders($token))->getJson('/api/v1/search?q=a');

    $response->assertOk()
        ->assertExactJson(['query' => 'a', 'groups' => []]);
});

it('GET /api/v1/search caps results per group', function () {
    ['tenant' => $tenant, 'token' => $token] = searchUser(['equipment.read']);
    Equipment::factory()->count(9)->create(['tenant_id' => $tenant->id, 'name' => 'Sensor de presión']);

    $response = $this->withHeaders(searchHeaders($token))->getJson('/api/v1/search?q=sensor');

    $response->assertOk();
    expect($response->json('groups.0.items'))->toHaveCount(5);
});

it('GET /api/v1/search requires authentication', function () {
    $this->getJson('/api/v1/search?q=test')->assertUnauthorized();
});
