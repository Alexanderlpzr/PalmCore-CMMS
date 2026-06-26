<?php

use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\Tenant;
use App\Models\User;

function tenantSetup(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('test-token', ['*']);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    $equipment = Equipment::factory()->for($tenant)->create();

    return [
        'tenant' => $tenant,
        'token' => $tokenResult->plainTextToken,
        'equipment' => $equipment,
    ];
}

// ── Tenant isolation ──────────────────────────────────────────────────────────

it('does not expose components from another tenant', function (): void {
    ['equipment' => $equipment, 'token' => $token] = tenantSetup();

    // Another tenant's equipment and component
    $otherTenant = Tenant::factory()->create();
    $otherEquipment = Equipment::factory()->for($otherTenant)->create();
    EquipmentComponent::factory()->forEquipment($otherEquipment)->count(3)->create();

    // Our equipment has no components
    $res = $this->getJson("/api/v1/equipment/{$equipment->id}/components", [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    expect($res->json('data'))->toBeEmpty();
});

it('cannot access equipment from another tenant', function (): void {
    ['token' => $token] = tenantSetup();

    // Equipment from a different tenant
    $otherTenant = Tenant::factory()->create();
    $otherEquipment = Equipment::factory()->for($otherTenant)->create();

    $this->getJson("/api/v1/equipment/{$otherEquipment->id}/components", [
        'Authorization' => 'Bearer '.$token,
    ])->assertNotFound();
});

it('component tenant_id is automatically set from equipment', function (): void {
    ['equipment' => $equipment, 'token' => $token, 'tenant' => $tenant] = tenantSetup();

    $res = $this->postJson("/api/v1/equipment/{$equipment->id}/components", [
        'name' => 'Componente auto-tenant',
    ], ['Authorization' => 'Bearer '.$token])->assertCreated();

    $component = EquipmentComponent::find($res->json('data.id'));
    expect($component->tenant_id)->toBe($tenant->id);
});

it('soft-deleted components are excluded from index', function (): void {
    ['equipment' => $equipment, 'token' => $token] = tenantSetup();

    $live = EquipmentComponent::factory()->forEquipment($equipment)->create(['name' => 'Activo']);
    $dead = EquipmentComponent::factory()->forEquipment($equipment)->create(['name' => 'Eliminado']);
    $dead->delete();

    $res = $this->getJson("/api/v1/equipment/{$equipment->id}/components", [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    $names = collect($res->json('data'))->pluck('name');
    expect($names)->toContain('Activo')->not->toContain('Eliminado');
});

it('same code is allowed across different equipment in same tenant', function (): void {
    ['tenant' => $tenant, 'token' => $token] = tenantSetup();

    $eq1 = Equipment::factory()->for($tenant)->create();
    $eq2 = Equipment::factory()->for($tenant)->create();

    EquipmentComponent::factory()->forEquipment($eq1)->create(['code' => 'MOTOR-01']);

    // Same code but different equipment — should succeed
    $this->postJson("/api/v1/equipment/{$eq2->id}/components", [
        'code' => 'MOTOR-01',
        'name' => 'Motor secundario',
    ], ['Authorization' => 'Bearer '.$token])->assertCreated();
});
