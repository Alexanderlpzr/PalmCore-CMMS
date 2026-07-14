<?php

use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertStatus;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Domain\Maintenance\Services\StaleMeterReadingService;
use App\Models\Alert;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * A7 — el horómetro que nadie leyó.
 *
 * Un plan preventivo por horas no falla con un error: falla en silencio. El
 * acumulado se queda quieto, la próxima OT nunca se proyecta, y la máquina sigue
 * trabajando horas que nadie cuenta. El programa preventivo se apaga solo y el
 * tablero sigue diciendo que el plan está «activo».
 */
beforeEach(function (): void {
    $this->service = app(StaleMeterReadingService::class);
    $this->meters = app(EquipmentMeterReadingService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'code' => 'PRE-02',
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);
    $this->operator = User::factory()->create();

    config()->set('palmcore.meters.stale_reading_days', 7);
});

function meterPlan(Equipment $equipment, array $attributes = []): MaintenancePlan
{
    return MaintenancePlan::factory()->create([
        'tenant_id' => $equipment->tenant_id,
        'equipment_id' => $equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
        'is_active' => true,
        ...$attributes,
    ]);
}

/** @return Collection<int, Alert> */
function staleAlerts(Tenant $tenant)
{
    return Alert::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('category', AlertCategory::Maintenance->value)
        ->where('status', AlertStatus::Open->value)
        ->get();
}

// ── Detectar ─────────────────────────────────────────────────────────────────

it('raises an alert when a meter-driven plan stops receiving readings', function (): void {
    meterPlan($this->equipment);

    $this->travelTo(now()->subDays(10));
    $this->meters->record($this->equipment, 1_200, $this->operator);
    $this->travelBack();

    expect($this->service->raiseAlerts($this->tenant->id))->toBe(1);

    $alert = staleAlerts($this->tenant)->sole();

    expect($alert->severity)->toBe(AlertSeverity::Warning)
        ->and($alert->entity_id)->toBe($this->equipment->id)
        ->and($alert->metadata['days_without_reading'])->toBe(10)
        ->and($alert->title)->toContain('PRE-02');
});

it('stays quiet while the readings keep coming', function (): void {
    meterPlan($this->equipment);

    $this->travelTo(now()->subDays(2));
    $this->meters->record($this->equipment, 1_200, $this->operator);
    $this->travelBack();

    expect($this->service->raiseAlerts($this->tenant->id))->toBe(0)
        ->and(staleAlerts($this->tenant))->toHaveCount(0);
});

it('measures a never-read equipment from the day its plan was born, not from the epoch', function (): void {
    // Un plan creado ayer no lleva 400 días roto: lleva uno. Inventar la antigüedad
    // sería exactamente el tipo de número que este sistema no puede permitirse.
    meterPlan($this->equipment, ['created_at' => now()->subDays(2)]);

    expect($this->service->detect($this->tenant->id))->toBeEmpty();

    meterPlan($this->equipment, ['created_at' => now()->subDays(30)]);

    $stale = $this->service->detect($this->tenant->id);

    expect($stale)->toHaveCount(1)
        ->and($stale[0]['days_without_reading'])->toBe(30)
        ->and($stale[0]['last_reading_at'])->toBeNull();
});

it('escalates to critical when the silence doubles the threshold', function (): void {
    meterPlan($this->equipment, ['created_at' => now()->subDays(20)]);

    $this->service->raiseAlerts($this->tenant->id);

    expect(staleAlerts($this->tenant)->sole()->severity)->toBe(AlertSeverity::Critical);
});

it('does not raise a second alert while the first is still open', function (): void {
    meterPlan($this->equipment, ['created_at' => now()->subDays(9)]);

    $this->service->raiseAlerts($this->tenant->id);
    $this->service->raiseAlerts($this->tenant->id);

    expect(staleAlerts($this->tenant))->toHaveCount(1);
});

// ── Lo que no es ruido ───────────────────────────────────────────────────────

it('ignores equipment whose preventive program does not depend on a meter', function (): void {
    // Alertar por un horómetro que no alimenta ningún plan es ruido, y el ruido es
    // lo que hace que se ignoren las alertas que sí importan.
    meterPlan($this->equipment, [
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'meter_interval' => null,
    ]);

    expect($this->service->detect($this->tenant->id))->toBeEmpty();
});

it('ignores a machine that is no longer in the plant', function (): void {
    $this->equipment->update(['status' => EquipmentStatus::Retired->value]);
    meterPlan($this->equipment, ['created_at' => now()->subDays(90)]);

    expect($this->service->detect($this->tenant->id))->toBeEmpty();
});

it('ignores an inactive plan', function (): void {
    meterPlan($this->equipment, ['is_active' => false, 'created_at' => now()->subDays(90)]);

    expect($this->service->detect($this->tenant->id))->toBeEmpty();
});

// ── Cerrar sola ──────────────────────────────────────────────────────────────

it('closes the alert by itself when the equipment finally speaks', function (): void {
    meterPlan($this->equipment, ['created_at' => now()->subDays(15)]);
    $this->service->raiseAlerts($this->tenant->id);

    expect(staleAlerts($this->tenant))->toHaveCount(1);

    // Llega la lectura de la ronda diaria: nadie tiene que ir a cerrar la alerta.
    $this->meters->record($this->equipment, 3_400, $this->operator);

    expect(staleAlerts($this->tenant))->toHaveCount(0)
        ->and(Alert::withoutGlobalScopes()->sole()->status)->toBe(AlertStatus::Resolved);
});

// ── Multi-tenant ─────────────────────────────────────────────────────────────

it('never looks at another tenant equipment', function (): void {
    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);
    $otherEquipment = Equipment::factory()->create([
        'tenant_id' => $other->id,
        'plant_id' => $otherPlant->id,
    ]);
    meterPlan($otherEquipment, ['created_at' => now()->subDays(90)]);

    expect($this->service->detect($this->tenant->id))->toBeEmpty()
        ->and($this->service->raiseAlerts($this->tenant->id))->toBe(0)
        ->and(Alert::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
});
