<?php

use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Services\DowntimeService;
use App\Exceptions\BusinessRuleException;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;

/**
 * Reclasificar la categoría física (Tipo I nuestro) de un paro cuando por fin se
 * sabe qué se rompió, sin tocar el Tipo I que reportó el cliente. La diferencia
 * entre los dos es el hallazgo. «Programado» no es un diagnóstico: eso lo decide el
 * origen del paro, no el hallazgo, así que no se puede reclasificar hacia ni desde él.
 */
beforeEach(function (): void {
    $this->downtime = app(DowntimeService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);
    $this->actor = User::factory()->create();
});

/** Un token del tenant, con las habilidades que se le pidan. */
function reclassifyHeaders(Tenant $tenant, array $abilities = ['downtime.write']): array
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $token = $user->createToken('test-token', $abilities);
    $token->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return ['Authorization' => 'Bearer '.$token->plainTextToken, 'Accept' => 'application/json'];
}

// ── Lo que el diagnóstico no puede hacer ─────────────────────────────────────

it('refuses to turn a failure into a planned stoppage', function (): void {
    $paro = EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Other->value,
        'was_planned' => false,
    ]);

    // Si «programado» fuera diagnosticable, cualquier falla incómoda saldría del
    // MTBF con un clic. El origen del paro lo decide, no el hallazgo.
    expect(fn () => $this->downtime->reclassify($paro, StoppageCategory::Planned))
        ->toThrow(BusinessRuleException::class);

    expect($paro->refresh()->stoppage_category)->toBe(StoppageCategory::Other);
});

it('refuses to reclassify a planned stoppage', function (): void {
    $paro = EquipmentDowntimeEvent::factory()->planned()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Planned->value,
    ]);

    expect(fn () => $this->downtime->reclassify($paro, StoppageCategory::Mechanical))
        ->toThrow(BusinessRuleException::class);
});

it('keeps the plant Tipo I untouched when we refine ours', function (): void {
    $paro = EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Other->value,
        'reported_type' => ReportedStoppageType::Operational->value,
        'was_planned' => false,
    ]);

    $this->downtime->reclassify($paro, StoppageCategory::Mechanical);

    // El Tipo I del cliente es suyo: nuestro diagnóstico va al lado, no encima.
    // La diferencia entre los dos es exactamente el hallazgo de la auditoría.
    expect($paro->refresh()->reported_type)->toBe(ReportedStoppageType::Operational)
        ->and($paro->stoppage_category)->toBe(StoppageCategory::Mechanical);
});

// ── API ──────────────────────────────────────────────────────────────────────

it('reclassifies a paro through the API', function (): void {
    $paro = EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Other->value,
        'was_planned' => false,
    ]);

    $this->withHeaders(reclassifyHeaders($this->tenant))
        ->patchJson("/api/v1/downtime-events/{$paro->id}/classify", [
            'stoppage_category' => StoppageCategory::Electrical->value,
            'stoppage_cause' => 'Contactor del motor principal',
        ])
        ->assertOk()
        ->assertJsonPath('data.stoppage_category', 'electrical')
        ->assertJsonPath('data.is_maintenance_responsibility', true);
});

it('rejects «programado» as a diagnosis through the API', function (): void {
    $paro = EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'was_planned' => false,
    ]);

    $this->withHeaders(reclassifyHeaders($this->tenant))
        ->patchJson("/api/v1/downtime-events/{$paro->id}/classify", [
            'stoppage_category' => StoppageCategory::Planned->value,
        ])
        ->assertStatus(422);
});

it('cannot reclassify a paro from another tenant', function (): void {
    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);
    $otherEquipment = Equipment::factory()->create([
        'tenant_id' => $other->id,
        'plant_id' => $otherPlant->id,
    ]);

    $foreign = EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $other->id,
        'plant_id' => $otherPlant->id,
        'equipment_id' => $otherEquipment->id,
        'stoppage_category' => StoppageCategory::Other->value,
        'was_planned' => false,
    ]);

    $this->withHeaders(reclassifyHeaders($this->tenant))
        ->patchJson("/api/v1/downtime-events/{$foreign->id}/classify", [
            'stoppage_category' => StoppageCategory::Mechanical->value,
        ])
        ->assertNotFound();

    expect($foreign->refresh()->stoppage_category)->toBe(StoppageCategory::Other);
});
