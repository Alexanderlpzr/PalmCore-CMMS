<?php

use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Maintenance\Enums\TimeLogActivityType;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTimeLog;
use Illuminate\Support\Carbon;

/**
 * M2 — el MTTR mide reparar, no esperar.
 *
 * Y la distancia entre los dos números es el hallazgo: si una falla tuvo 2 h de
 * llave y 7 h de espera de repuesto, la máquina estuvo 9 h abajo. Ninguno de los
 * dos números sobra, y ninguno puede taparse con el otro.
 */
beforeEach(function (): void {
    $this->service = app(PlantKpiService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);
});

/** Una falla de 9 h de paro con la OT que la atendió. */
function failureWithWorkOrder(Tenant $tenant, Plant $plant, Equipment $equipment, string $startedAt, float $downtimeHours): WorkOrder
{
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'plant_id' => $plant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective,
        'status' => WorkOrderStatus::Completed,
        'equipment_stopped' => true,
    ]);

    $start = Carbon::parse($startedAt);

    EquipmentDowntimeEvent::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'plant_id' => $plant->id,
        'equipment_id' => $equipment->id,
        'work_order_id' => $workOrder->id,
        'started_at' => $start,
        'ended_at' => $start->copy()->addMinutes((int) round($downtimeHours * 60)),
        'duration_minutes' => (int) round($downtimeHours * 60),
        'cause_type' => 'corrective',
        'stoppage_category' => StoppageCategory::Mechanical->value,
        'was_planned' => false,
        'affects_production' => true,
        'source' => 'work_order',
    ]);

    return $workOrder;
}

function logActivity(WorkOrder $workOrder, TimeLogActivityType $activity, float $hours): void
{
    WorkOrderTimeLog::withoutGlobalScopes()->create([
        'tenant_id' => $workOrder->tenant_id,
        'work_order_id' => $workOrder->id,
        'user_id' => User::factory()->create()->id,
        'started_at' => now(),
        'ended_at' => now()->addMinutes((int) round($hours * 60)),
        'hours' => $hours,
        'activity_type' => $activity->value,
    ]);
}

/** Un token de API de un usuario que pertenece a este tenant. */
function apiTokenFor(Tenant $tenant): string
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $token = $user->createToken('activity-test', ['*']);
    $token->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return $token->plainTextToken;
}

/** @return array<string, mixed> */
function kpisOfJune(): array
{
    return test()->service->calculate(
        test()->plant,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30 23:59:59'),
    );
}

// ── Los dos números ──────────────────────────────────────────────────────────

it('separates wrench time from waiting for the part', function (): void {
    $workOrder = failureWithWorkOrder($this->tenant, $this->plant, $this->equipment, '2026-06-10 08:00:00', 9.0);

    logActivity($workOrder, TimeLogActivityType::Diagnosis, 0.5);
    logActivity($workOrder, TimeLogActivityType::WaitingParts, 7.0);
    logActivity($workOrder, TimeLogActivityType::Repair, 1.5);

    $kpis = kpisOfJune();

    expect($kpis['wrench_hours'])->toBe(2.0)
        ->and($kpis['waiting_hours'])->toBe(7.0)
        // Se reparó en 2 h. La máquina estuvo 9 h abajo. Las dos cosas son ciertas.
        ->and($kpis['mttr_wrench_hours'])->toBe(2.0)
        ->and($kpis['mttr_hours'])->toBe(9.0);
});

it('does not let the wrench MTTR flatter a plant that only waited', function (): void {
    $workOrder = failureWithWorkOrder($this->tenant, $this->plant, $this->equipment, '2026-06-10 08:00:00', 12.0);

    logActivity($workOrder, TimeLogActivityType::WaitingParts, 11.0);
    logActivity($workOrder, TimeLogActivityType::Repair, 1.0);

    // La OT «se resolvió en una hora», pero la planta perdió doce.
    expect(kpisOfJune()['mttr_wrench_hours'])->toBe(1.0)
        ->and(kpisOfJune()['mttr_hours'])->toBe(12.0)
        ->and(kpisOfJune()['waiting_hours'])->toBe(11.0);
});

it('averages the wrench time over the failures that were classified', function (): void {
    $first = failureWithWorkOrder($this->tenant, $this->plant, $this->equipment, '2026-06-10 08:00:00', 4.0);
    $second = failureWithWorkOrder($this->tenant, $this->plant, $this->equipment, '2026-06-12 08:00:00', 6.0);

    logActivity($first, TimeLogActivityType::Repair, 3.0);
    logActivity($second, TimeLogActivityType::Repair, 1.0);

    expect(kpisOfJune()['classified_failure_count'])->toBe(2)
        ->and(kpisOfJune()['mttr_wrench_hours'])->toBe(2.0);
});

// ── Lo que no se midió no se inventa ─────────────────────────────────────────

it('reports no wrench MTTR when nobody classified what the technician was doing', function (): void {
    $workOrder = failureWithWorkOrder($this->tenant, $this->plant, $this->equipment, '2026-06-10 08:00:00', 9.0);

    // Un log sin clasificar no es «reparación»: nadie le preguntó al técnico.
    WorkOrderTimeLog::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $workOrder->id,
        'user_id' => User::factory()->create()->id,
        'started_at' => now(),
        'ended_at' => now()->addHours(2),
        'hours' => 2.0,
    ]);

    $kpis = kpisOfJune();

    expect($kpis['mttr_wrench_hours'])->toBeNull()
        ->and($kpis['classified_failure_count'])->toBe(0)
        ->and($kpis['wrench_hours'])->toBe(0.0)
        // El número que le duele a producción sigue existiendo: el paro se midió.
        ->and($kpis['mttr_hours'])->toBe(9.0);
});

it('does not average over a paro that has no work order behind it', function (): void {
    // Paro registrado por el supervisor, sin OT: no hay tiempo de llave que medir.
    EquipmentDowntimeEvent::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'started_at' => '2026-06-10 08:00:00',
        'ended_at' => '2026-06-10 12:00:00',
        'duration_minutes' => 240,
        'cause_type' => 'corrective',
        'stoppage_category' => StoppageCategory::Mechanical->value,
        'was_planned' => false,
        'affects_production' => true,
        'source' => 'manual',
    ]);

    $kpis = kpisOfJune();

    expect($kpis['failure_count'])->toBe(1)
        ->and($kpis['classified_failure_count'])->toBe(0)
        ->and($kpis['mttr_wrench_hours'])->toBeNull();
});

// ── El técnico tiene que poder decirlo ───────────────────────────────────────

it('lets the technician report what he was doing through the API', function (): void {
    $token = apiTokenFor($this->tenant);

    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    $this->postJson(
        "/api/v1/work-orders/{$workOrder->id}/time-entries",
        [
            'started_at' => now()->subHours(3)->toISOString(),
            'ended_at' => now()->toISOString(),
            'activity_type' => TimeLogActivityType::WaitingParts->value,
        ],
        ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'],
    )
        ->assertCreated()
        ->assertJsonPath('data.activity_type', 'waiting_parts')
        ->assertJsonPath('data.activity_label', 'Espera de repuesto')
        ->assertJsonPath('data.is_wrench_time', false);
});

it('rejects an activity nobody defined', function (): void {
    $token = apiTokenFor($this->tenant);

    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    $this->postJson(
        "/api/v1/work-orders/{$workOrder->id}/time-entries",
        ['started_at' => now()->toISOString(), 'activity_type' => 'tomando_tinto'],
        ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'],
    )->assertStatus(422);
});

// ── Multi-tenant ─────────────────────────────────────────────────────────────

it('never counts another tenant hours as wrench time', function (): void {
    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);
    $otherEquipment = Equipment::factory()->create([
        'tenant_id' => $other->id,
        'plant_id' => $otherPlant->id,
    ]);

    $theirs = failureWithWorkOrder($other, $otherPlant, $otherEquipment, '2026-06-10 08:00:00', 9.0);
    logActivity($theirs, TimeLogActivityType::Repair, 5.0);

    $kpis = kpisOfJune();

    expect($kpis['wrench_hours'])->toBe(0.0)
        ->and($kpis['mttr_wrench_hours'])->toBeNull();
});
