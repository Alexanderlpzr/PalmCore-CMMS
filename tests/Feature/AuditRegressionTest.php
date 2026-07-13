<?php

use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\MaintenancePlanService;
use App\Domain\Maintenance\Services\PreventiveWorkOrderGenerator;
use App\Events\WorkOrderStatusChanged;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\EquipmentMeterReading;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceSchedule;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

/**
 * Regresiones de la segunda auditoría (2026-07-13).
 *
 * Cada test aquí corresponde a un bug de flujo que el sistema tenía y que estos
 * tests impiden que vuelva.
 */

// ── Helpers ───────────────────────────────────────────────────────────────────

function auditToken(Tenant $tenant): string
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);
    $token = $user->createToken('audit', ['*']);
    $token->accessToken->forceFill(['tenant_id' => $tenant->id])->save();

    return $token->plainTextToken;
}

function auditHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

// ── BUG 1 — Aislamiento multi-tenant en las entradas por id ──────────────────

it('never writes a meter reading onto another tenant equipment', function (): void {
    $mine = Tenant::factory()->create();
    $theirs = Tenant::factory()->create();
    $victim = Equipment::factory()->create([
        'tenant_id' => $theirs->id,
        'current_meter_reading' => 100,
        'accumulated_meter_reading' => 100,
    ]);

    $this->withHeaders(auditHeaders(auditToken($mine)))
        ->postJson('/api/v1/meter-readings/bulk', [
            'readings' => [['equipment_id' => $victim->id, 'reading_value' => 99_999]],
        ])
        ->assertCreated()
        // La fila se rechaza como fallida, no se escribe.
        ->assertJsonPath('meta.recorded', 0);

    expect(EquipmentMeterReading::withoutGlobalScopes()->where('equipment_id', $victim->id)->count())->toBe(0)
        ->and($victim->refresh()->current_meter_reading)->toBe(100.0);
});

it('never attaches a stoppage to another tenant equipment', function (): void {
    $mine = Tenant::factory()->create();
    $myPlant = Plant::factory()->create(['tenant_id' => $mine->id]);
    $theirs = Tenant::factory()->create();
    $victim = Equipment::factory()->create(['tenant_id' => $theirs->id]);

    $this->withHeaders(auditHeaders(auditToken($mine)))
        ->postJson('/api/v1/downtime-events', [
            'plant_id' => $myPlant->id,
            'equipment_id' => $victim->id,
            'stoppage_category' => StoppageCategory::Mechanical->value,
        ])
        ->assertStatus(409);

    expect(EquipmentDowntimeEvent::withoutGlobalScopes()->where('equipment_id', $victim->id)->count())->toBe(0);
});

it('never attaches a stoppage to another tenant plant', function (): void {
    $mine = Tenant::factory()->create();
    $theirs = Tenant::factory()->create();
    $victimPlant = Plant::factory()->create(['tenant_id' => $theirs->id]);

    $this->withHeaders(auditHeaders(auditToken($mine)))
        ->postJson('/api/v1/downtime-events', [
            'plant_id' => $victimPlant->id,
            'stoppage_category' => StoppageCategory::RawMaterial->value,
        ])
        ->assertStatus(409);

    expect(EquipmentDowntimeEvent::withoutGlobalScopes()->where('plant_id', $victimPlant->id)->count())->toBe(0);
});

// ── BUG 2 — El plan avanzaba dos veces si el supervisor rechazaba la OT ──────

it('advances the plan schedule only once when a rejected preventive is completed again', function (): void {
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => now()->subDay(),
        'next_due_meter' => null,
    ]);

    app(PreventiveWorkOrderGenerator::class)
        ->generateForTenant($tenant->id, User::factory()->create());

    $workOrder = WorkOrder::withoutGlobalScopes()->first();
    $workOrder->update(['status' => WorkOrderStatus::Completed->value, 'actual_end_at' => now()]);

    // El técnico completa, el supervisor rechaza, el técnico vuelve a completar.
    event(new WorkOrderStatusChanged($workOrder->refresh(), WorkOrderStatus::Completed));
    event(new WorkOrderStatusChanged($workOrder->refresh(), WorkOrderStatus::Completed));

    // Una ejecución es una ejecución, por muchas veces que se firme.
    expect($plan->schedule->refresh()->times_executed)->toBe(1);
});

// ── BUG 4 — Un plan por horómetro nunca emitía su primera OT ─────────────────

it('gives a meter-driven plan a due point when it is activated without one', function (): void {
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create([
        'tenant_id' => $tenant->id,
        'accumulated_meter_reading' => 1_200,
    ]);

    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
        'is_active' => false,
    ]);

    // Se activa sin escribir a mano el primer objetivo — el caso normal.
    $schedule = app(MaintenancePlanService::class)->activate($plan);

    // Sin esto el plan quedaba «activo» y mudo para siempre: sin punto de
    // vencimiento, el generador nunca lo mira.
    expect($schedule->next_due_meter)->toBe(1700.0);
});

// ── BUG 5 — Una firma tardía cancelaba en silencio el siguiente preventivo ───

it('generates the next preventive even if the previous one is not administratively closed', function (): void {
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $actor = User::factory()->create();

    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => now()->subDay(),
        'next_due_meter' => null,
    ]);

    $generator = app(PreventiveWorkOrderGenerator::class);
    $generator->generateForTenant($tenant->id, $actor);

    // El técnico la terminó; el supervisor todavía no la ha cerrado.
    $first = WorkOrder::withoutGlobalScopes()->first();
    $first->update(['status' => WorkOrderStatus::Completed->value]);

    // Y el plan vuelve a vencer.
    $plan->schedule->update(['next_due_at' => now()->subDay()]);

    expect($generator->generateForTenant($tenant->id, $actor)['generated'])->toBe(1)
        ->and(WorkOrder::withoutGlobalScopes()->count())->toBe(2);
});

it('still refuses to pile a second work order on a plan whose OT is unfinished', function (): void {
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $actor = User::factory()->create();

    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => now()->subDay(),
        'next_due_meter' => null,
    ]);

    $generator = app(PreventiveWorkOrderGenerator::class);
    $generator->generateForTenant($tenant->id, $actor);
    $generator->generateForTenant($tenant->id, $actor);

    expect(WorkOrder::withoutGlobalScopes()->count())->toBe(1);
});

// ── BUG 3 — El MTBF de planta ignoraba los correctivos ───────────────────────

it('counts a stoppage born from a corrective work order as a maintenance failure', function (): void {
    $tenant = Tenant::factory()->create();
    $plant = Plant::factory()->create(['tenant_id' => $tenant->id]);
    $equipment = Equipment::factory()->create([
        'tenant_id' => $tenant->id,
        'plant_id' => $plant->id,
    ]);

    ProductionCalendarDay::create([
        'tenant_id' => $tenant->id,
        'plant_id' => $plant->id,
        'calendar_date' => now()->startOfMonth()->toDateString(),
        'programmed_hours' => 20,
    ]);

    // Así nace un paro desde una OT correctiva: su Tipo I aún es «otro», porque
    // la OT no sabe si la causa fue mecánica o eléctrica.
    $startedAt = now()->startOfMonth()->addHours(2);
    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'plant_id' => $plant->id,
        'equipment_id' => $equipment->id,
        'stoppage_category' => StoppageCategory::Other->value,
        'source' => 'work_order',
        'was_planned' => false,
        'affects_production' => true,
        'started_at' => $startedAt,
        'ended_at' => $startedAt->copy()->addHours(4),
        'duration_minutes' => 240,
    ]);

    $kpis = app(PlantKpiService::class)->calculate($plant, now()->startOfMonth(), now()->endOfMonth());

    // Antes: 0 fallas y MTBF nulo — el número que existe para medir los
    // correctivos era ciego a los correctivos.
    expect($kpis['failure_count'])->toBe(1)
        ->and($kpis['maintenance_lost_hours'])->toBe(4.0)
        ->and($kpis['mttr_hours'])->toBe(4.0)
        ->and($kpis['mtbf_hours'])->toBe(16.0);
});

it('still does not blame maintenance for a stoppage it does not own', function (): void {
    $tenant = Tenant::factory()->create();
    $plant = Plant::factory()->create(['tenant_id' => $tenant->id]);

    $startedAt = now()->startOfMonth()->addHours(2);
    EquipmentDowntimeEvent::factory()->plantWide()->create([
        'tenant_id' => $tenant->id,
        'plant_id' => $plant->id,
        'source' => 'manual',
        'was_planned' => false,
        'affects_production' => true,
        'started_at' => $startedAt,
        'ended_at' => $startedAt->copy()->addHours(6),
        'duration_minutes' => 360,
    ]);

    $kpis = app(PlantKpiService::class)->calculate($plant, now()->startOfMonth(), now()->endOfMonth());

    // Falta de fruta: 6 h perdidas, cero fallas de mantenimiento.
    expect($kpis['lost_hours'])->toBe(6.0)
        ->and($kpis['maintenance_lost_hours'])->toBe(0.0)
        ->and($kpis['failure_count'])->toBe(0);
});
