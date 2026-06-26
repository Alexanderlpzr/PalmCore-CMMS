<?php

use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\Tenant;
use App\Models\User;

function policySetup(array $abilities): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $tokenResult = $user->createToken('test-token', $abilities);
    $tokenResult->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    $equipment = Equipment::factory()->for($tenant)->create();

    return [
        'tenant' => $tenant,
        'token' => $tokenResult->plainTextToken,
        'equipment' => $equipment,
    ];
}

// ── Read ability ──────────────────────────────────────────────────────────────

it('requires equipment.read ability to list components', function (): void {
    ['equipment' => $equipment, 'token' => $token] = policySetup(['work-orders.read']);

    $this->getJson("/api/v1/equipment/{$equipment->id}/components", [
        'Authorization' => 'Bearer '.$token,
    ])->assertForbidden();
});

it('allows equipment.read ability to list components', function (): void {
    ['equipment' => $equipment, 'token' => $token] = policySetup(['equipment.read']);

    $this->getJson("/api/v1/equipment/{$equipment->id}/components", [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();
});

it('allows wildcard (*) ability to list components', function (): void {
    ['equipment' => $equipment, 'token' => $token] = policySetup(['*']);

    $this->getJson("/api/v1/equipment/{$equipment->id}/components", [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();
});

// ── Write ability ─────────────────────────────────────────────────────────────

it('requires equipment.write ability to create a component', function (): void {
    ['equipment' => $equipment, 'token' => $token] = policySetup(['equipment.read']);

    $this->postJson("/api/v1/equipment/{$equipment->id}/components", [
        'name' => 'Componente sin permiso',
    ], ['Authorization' => 'Bearer '.$token])->assertForbidden();
});

it('allows equipment.write ability to create a component', function (): void {
    ['equipment' => $equipment, 'token' => $token] = policySetup(['equipment.write']);

    $this->postJson("/api/v1/equipment/{$equipment->id}/components", [
        'name' => 'Componente con permiso',
    ], ['Authorization' => 'Bearer '.$token])->assertCreated();
});

it('requires equipment.write ability to delete a component', function (): void {
    ['equipment' => $equipment, 'token' => $token, 'tenant' => $tenant] = policySetup(['equipment.read']);

    $component = EquipmentComponent::factory()->forEquipment($equipment)->create();

    $this->deleteJson("/api/v1/equipment/{$equipment->id}/components/{$component->id}", [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertForbidden();
});

it('requires authentication to access any component endpoint', function (): void {
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->for($tenant)->create();

    $this->getJson("/api/v1/equipment/{$equipment->id}/components")->assertUnauthorized();
});
