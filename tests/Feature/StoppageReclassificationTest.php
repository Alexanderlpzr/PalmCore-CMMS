<?php

use App\Domain\Assets\Enums\EquipmentDowntimeCauseType;
use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Services\DowntimeService;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Exceptions\BusinessRuleException;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

/**
 * A4 — el Tipo I que solo se sabe al destapar la máquina.
 *
 * Una OT correctiva abre su paro en «otro»: al arrancar nadie sabe si el problema
 * era mecánico o eléctrico, y adivinarlo sería inventar el dato. El técnico lo
 * afina al diagnosticar. Sin esto, el Pareto de horas perdidas es una montaña de
 * «otro» que no le dice a nadie dónde intervenir.
 */
beforeEach(function (): void {
    $this->downtime = app(DowntimeService::class);
    $this->workOrders = app(WorkOrderService::class);
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

/**
 * Una OT de emergencia arranca ya en ejecución, que es como llega la falla real:
 * la prensa se paró y alguien ya está con la llave en la mano.
 */
function correctiveWorkOrder(bool $stopped = true): WorkOrder
{
    return app(WorkOrderService::class)->create([
        'tenant_id' => test()->tenant->id,
        'equipment_id' => test()->equipment->id,
        'work_order_type' => WorkOrderType::Emergency->value,
        'priority' => WorkOrderPriority::P2High->value,
        'title' => 'Falla en el reductor de la prensa 2',
        'description' => 'La prensa 2 se detuvo con ruido en el reductor.',
        'equipment_stopped' => $stopped,
    ], test()->actor);
}

// ── El diagnóstico baja al paro ──────────────────────────────────────────────

it('refines the Tipo I of the paro when the técnico diagnoses the failure', function (): void {
    $workOrder = correctiveWorkOrder();

    // Al abrir, la OT no sabía qué se había roto.
    $paro = EquipmentDowntimeEvent::withoutGlobalScopes()->where('work_order_id', $workOrder->id)->first();
    expect($paro->stoppage_category)->toBe(StoppageCategory::Other);

    // El técnico destapa el reductor: era mecánico.
    $this->workOrders->transition($workOrder, WorkOrderStatus::Completed, $this->actor, [
        'work_performed' => 'Cambio de rodamiento del reductor',
        'failure_cause' => 'Rodamiento del piñón intermedio',
        'diagnosed_stoppage_category' => StoppageCategory::Mechanical->value,
    ]);

    $paro->refresh();

    expect($paro->stoppage_category)->toBe(StoppageCategory::Mechanical)
        // El cause_type legado queda coherente, o los KPIs viejos verían otra cosa.
        ->and($paro->cause_type)->toBe(EquipmentDowntimeCauseType::Corrective)
        ->and($paro->stoppage_cause)->toBe('Rodamiento del piñón intermedio');
});

it('refines a failure that never stopped the line', function (): void {
    // Falla puntual: la máquina siguió andando, pero la falla existe y cuenta al MTBF.
    $workOrder = correctiveWorkOrder(stopped: false);
    $this->workOrders->transition($workOrder, WorkOrderStatus::Completed, $this->actor, [
        'work_performed' => 'Ajuste de sensor',
        'diagnosed_stoppage_category' => StoppageCategory::Instrumentation->value,
    ]);

    $paro = EquipmentDowntimeEvent::withoutGlobalScopes()->where('work_order_id', $workOrder->id)->first();

    expect($paro->stoppage_category)->toBe(StoppageCategory::Instrumentation)
        // Sigue sin restarle horas a la planta: no detuvo la línea.
        ->and($paro->affects_production)->toBeFalse();
});

it('leaves the paro alone when the OT was completed without a diagnosis', function (): void {
    $workOrder = correctiveWorkOrder();
    $this->workOrders->transition($workOrder, WorkOrderStatus::Completed, $this->actor, [
        'work_performed' => 'Se destrabó la banda',
    ]);

    expect(EquipmentDowntimeEvent::withoutGlobalScopes()->where('work_order_id', $workOrder->id)->first()->stoppage_category)
        ->toBe(StoppageCategory::Other);
});

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
