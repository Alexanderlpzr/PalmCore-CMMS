<?php

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\Tenant;
use App\Models\User;

function componentApiSetup(array $abilities = ['equipment.read', 'equipment.write']): array
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

// ── Index ─────────────────────────────────────────────────────────────────────

it('returns empty list when equipment has no components', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentApiSetup();

    $res = $this->getJson("/api/v1/equipment/{$equipment->id}/components", [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    expect($res->json('data'))->toBeArray()->toBeEmpty();
});

it('lists components for an equipment', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentApiSetup();

    EquipmentComponent::factory()->forEquipment($equipment)->count(4)->create();

    $res = $this->getJson("/api/v1/equipment/{$equipment->id}/components", [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    expect($res->json('data'))->toHaveCount(4);
});

it('returns 404 when equipment does not exist', function (): void {
    ['token' => $token] = componentApiSetup();

    $this->getJson('/api/v1/equipment/00000000-0000-0000-0000-000000000000/components', [
        'Authorization' => 'Bearer '.$token,
    ])->assertNotFound();
});

// ── Store ─────────────────────────────────────────────────────────────────────

it('creates a component with required fields only', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentApiSetup();

    $res = $this->postJson("/api/v1/equipment/{$equipment->id}/components", [
        'name' => 'Rodamiento principal',
    ], ['Authorization' => 'Bearer '.$token])->assertCreated();

    expect($res->json('data.name'))->toBe('Rodamiento principal');
    expect($res->json('data.criticality'))->toBe('medium');
    expect($res->json('data.equipment_id'))->toBe($equipment->id);
});

it('creates a component with all optional fields', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentApiSetup();

    $res = $this->postJson("/api/v1/equipment/{$equipment->id}/components", [
        'code' => 'ROD-001',
        'name' => 'Rodamiento axial',
        'manufacturer' => 'SKF',
        'model' => '6205-2RS',
        'serial_number' => 'SN-12345',
        'criticality' => 'critical',
        'useful_life_hours' => 8000,
        'notes' => 'Revisar cada 6 meses',
    ], ['Authorization' => 'Bearer '.$token])->assertCreated();

    expect($res->json('data.code'))->toBe('ROD-001');
    expect($res->json('data.manufacturer'))->toBe('SKF');
    expect($res->json('data.criticality'))->toBe('critical');
    expect($res->json('data.useful_life_hours'))->toBe(8000);
});

it('rejects duplicate code within same equipment', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentApiSetup();

    EquipmentComponent::factory()->forEquipment($equipment)->create(['code' => 'ROD-001']);

    $this->postJson("/api/v1/equipment/{$equipment->id}/components", [
        'code' => 'ROD-001',
        'name' => 'Otro componente',
    ], ['Authorization' => 'Bearer '.$token])->assertUnprocessable();
});

it('rejects invalid criticality value', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentApiSetup();

    $this->postJson("/api/v1/equipment/{$equipment->id}/components", [
        'name' => 'Componente',
        'criticality' => 'extreme',
    ], ['Authorization' => 'Bearer '.$token])->assertUnprocessable();
});

// ── Show ──────────────────────────────────────────────────────────────────────

it('shows a single component', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentApiSetup();

    $component = EquipmentComponent::factory()->forEquipment($equipment)->create(['name' => 'Motor eléctrico']);

    $res = $this->getJson("/api/v1/equipment/{$equipment->id}/components/{$component->id}", [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    expect($res->json('data.name'))->toBe('Motor eléctrico');
});

// ── Horas de vida: el bug que no puede volver ────────────────────────────────

it('anchors worked_hours to the equipment meter reading at creation', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentApiSetup();
    $equipment->update(['accumulated_meter_reading' => 3000]);

    $res = $this->postJson("/api/v1/equipment/{$equipment->id}/components", [
        'name' => 'Rodamiento principal',
        'worked_hours' => 200,
    ], ['Authorization' => 'Bearer '.$token])->assertCreated();

    $component = EquipmentComponent::find($res->json('data.id'));

    expect($component->worked_hours)->toBe(200.0)
        ->and($component->meter_reading_baseline)->toBe(3000.0);
});

it('rebaselines worked_hours when corrected through the API', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentApiSetup();
    $component = EquipmentComponent::factory()->forEquipment($equipment)->create([
        'worked_hours' => 4500,
        'meter_reading_baseline' => 6000,
    ]);
    $equipment->update(['accumulated_meter_reading' => 8000]);

    $this->patchJson("/api/v1/equipment/{$equipment->id}/components/{$component->id}", [
        'worked_hours' => 0,
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    expect($component->refresh()->worked_hours)->toBe(0.0)
        ->and($component->meter_reading_baseline)->toBe(8000.0);
});

it('clears the baseline when worked_hours is explicitly set to null', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentApiSetup();
    $component = EquipmentComponent::factory()->forEquipment($equipment)->create([
        'worked_hours' => 500,
        'meter_reading_baseline' => 2000,
    ]);

    $this->patchJson("/api/v1/equipment/{$equipment->id}/components/{$component->id}", [
        'worked_hours' => null,
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    expect($component->refresh()->worked_hours)->toBeNull()
        ->and($component->meter_reading_baseline)->toBeNull();
});

it('does not touch the baseline when updating fields other than worked_hours', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentApiSetup();
    $component = EquipmentComponent::factory()->forEquipment($equipment)->create([
        'worked_hours' => 500,
        'meter_reading_baseline' => 2000,
    ]);

    $this->patchJson("/api/v1/equipment/{$equipment->id}/components/{$component->id}", [
        'name' => 'Nombre corregido',
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    expect($component->refresh()->worked_hours)->toBe(500.0)
        ->and($component->meter_reading_baseline)->toBe(2000.0);
});

// ── Update ────────────────────────────────────────────────────────────────────

it('updates component name and criticality', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentApiSetup();

    $component = EquipmentComponent::factory()->forEquipment($equipment)->create(['name' => 'Bomba original']);

    $res = $this->patchJson("/api/v1/equipment/{$equipment->id}/components/{$component->id}", [
        'name' => 'Bomba actualizada',
        'criticality' => 'high',
    ], ['Authorization' => 'Bearer '.$token])->assertOk();

    expect($res->json('data.name'))->toBe('Bomba actualizada');
    expect($res->json('data.criticality'))->toBe('high');
});

it('allows updating code to the same value on same component', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentApiSetup();

    $component = EquipmentComponent::factory()->forEquipment($equipment)->create(['code' => 'ROD-001']);

    $this->patchJson("/api/v1/equipment/{$equipment->id}/components/{$component->id}", [
        'code' => 'ROD-001',
        'name' => $component->name,
    ], ['Authorization' => 'Bearer '.$token])->assertOk();
});

// ── Destroy ───────────────────────────────────────────────────────────────────

it('soft-deletes a component', function (): void {
    ['equipment' => $equipment, 'token' => $token] = componentApiSetup();

    $component = EquipmentComponent::factory()->forEquipment($equipment)->create();

    $this->deleteJson("/api/v1/equipment/{$equipment->id}/components/{$component->id}", [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertNoContent();

    expect(EquipmentComponent::find($component->id))->toBeNull();
    expect(EquipmentComponent::withTrashed()->find($component->id))->not->toBeNull();
});

// ── Factory states ────────────────────────────────────────────────────────────

it('factory creates a valid component with correct relationships', function (): void {
    ['equipment' => $equipment] = componentApiSetup();

    $component = EquipmentComponent::factory()->forEquipment($equipment)->create();

    expect($component->name)->toBeString()->not->toBeEmpty();
    expect($component->criticality)->toBeInstanceOf(EquipmentCriticality::class);
    expect($component->equipment_id)->toBe($equipment->id);
    expect($component->tenant_id)->toBe($equipment->tenant_id);
});

it('factory critical() state sets critical criticality', function (): void {
    ['equipment' => $equipment] = componentApiSetup();

    $component = EquipmentComponent::factory()->forEquipment($equipment)->critical()->create();

    expect($component->criticality)->toBe(EquipmentCriticality::Critical);
});
