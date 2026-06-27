<?php

use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

function componentsTestSetup(array $abilities = ['equipment.read', 'equipment.write']): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('test-token', $abilities);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    $equipment = Equipment::factory()->for($tenant)->create();

    return [
        'tenant' => $tenant,
        'user' => $user,
        'token' => $tokenResult->plainTextToken,
        'equipment' => $equipment,
    ];
}

// ── 1. Tree endpoint returns nested children ──────────────────────────────────

it('tree endpoint returns nested children', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentsTestSetup();

    $parent = EquipmentComponent::factory()->forEquipment($equipment)->create(['name' => 'Motor principal']);
    EquipmentComponent::factory()->forEquipment($equipment)->create([
        'parent_id' => $parent->id,
        'name' => 'Rodamiento',
    ]);

    $res = $this->getJson("/api/v1/equipment/{$equipment->id}/components/tree", [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $data = $res->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['name'])->toBe('Motor principal');
    expect($data[0]['children'])->toHaveCount(1);
    expect($data[0]['children'][0]['name'])->toBe('Rodamiento');
});

// ── 2. Store component with new fields ────────────────────────────────────────

it('stores component with part_number, status, and worked_hours', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentsTestSetup();

    $res = $this->postJson("/api/v1/equipment/{$equipment->id}/components", [
        'name' => 'Bomba centrífuga',
        'part_number' => 'PN-4501-X',
        'status' => 'degraded',
        'worked_hours' => 1250.5,
    ], ['Authorization' => 'Bearer '.$token])->assertCreated();

    expect($res->json('data.part_number'))->toBe('PN-4501-X');
    expect($res->json('data.status'))->toBe('degraded');
    expect($res->json('data.worked_hours'))->toBe(1250.5);
});

// ── 3. History index returns entries ──────────────────────────────────────────

it('history index returns entries for a component', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentsTestSetup();

    $component = EquipmentComponent::factory()->forEquipment($equipment)->create();

    $component->history()->create([
        'tenant_id' => $equipment->tenant_id,
        'type' => 'maintenance',
        'description' => 'Mantenimiento preventivo',
        'occurred_at' => now()->subDays(5),
    ]);

    $res = $this->getJson("/api/v1/equipment/{$equipment->id}/components/{$component->id}/history", [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    expect($res->json('data'))->toHaveCount(1);
    expect($res->json('data.0.type'))->toBe('maintenance');
});

// ── 4. History store creates entry with user_id from auth ─────────────────────

it('history store creates entry with user_id from auth', function (): void {
    ['equipment' => $equipment, 'token' => $token, 'user' => $user] = componentsTestSetup();

    $component = EquipmentComponent::factory()->forEquipment($equipment)->create();

    $res = $this->postJson("/api/v1/equipment/{$equipment->id}/components/{$component->id}/history", [
        'type' => 'inspection',
        'description' => 'Inspección visual realizada',
        'worked_hours_at_event' => 500.0,
    ], ['Authorization' => 'Bearer '.$token])->assertCreated();

    expect($res->json('data.type'))->toBe('inspection');
    expect($res->json('data.user.id'))->toBe($user->id);
    expect($component->history()->count())->toBe(1);
});

// ── 5. Tenant isolation: history of component in tenant A → 404 for tenant B ──

it('history of component in tenant A returns 404 for tenant B user', function (): void {
    // Tenant A with equipment and component
    $tenantA = Tenant::factory()->create();
    $equipmentA = Equipment::factory()->for($tenantA)->create();
    $componentA = EquipmentComponent::factory()->forEquipment($equipmentA)->create();

    // Tenant B user
    $tenantB = Tenant::factory()->create();
    $userB = User::factory()->create(['is_active' => true]);
    $userB->tenants()->attach($tenantB->id, ['joined_at' => now()]);
    $tokenResult = $userB->createToken('b-token', ['*']);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenantB->id])->save();
    $tokenB = $tokenResult->plainTextToken;

    $this->getJson("/api/v1/equipment/{$equipmentA->id}/components/{$componentA->id}/history", [
        'Authorization' => 'Bearer '.$tokenB,
    ])->assertNotFound();
});

// ── 6. WorkOrder can reference equipment_component_id ─────────────────────────

it('work order can reference equipment_component_id', function (): void {
    ['equipment' => $equipment, 'tenant' => $tenant] = componentsTestSetup();

    $component = EquipmentComponent::factory()->forEquipment($equipment)->create();

    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'equipment_component_id' => $component->id,
    ]);

    expect($workOrder->component)->not->toBeNull();
    expect($workOrder->component->id)->toBe($component->id);
    expect($workOrder->fresh()->equipment_component_id)->toBe($component->id);
});
