<?php

use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Enums\StoppageReason;
use App\Domain\Assets\Services\DowntimeService;
use App\Exceptions\BusinessRuleException;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function (): void {
    $this->service = app(DowntimeService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);
    $this->actor = User::factory()->create();
});

/** Registra un paro cerrado del equipo de pruebas. */
function paroCerrado(array $overrides = []): EquipmentDowntimeEvent
{
    return test()->service->register([
        'tenant_id' => test()->tenant->id,
        'plant_id' => test()->plant->id,
        'equipment_id' => test()->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'affects_production' => true,
        'started_at' => now()->startOfMonth()->setTime(8, 0),
        'ended_at' => now()->startOfMonth()->setTime(10, 0),
        ...$overrides,
    ], test()->actor);
}

// ── Corregir de verdad ───────────────────────────────────────────────────────

it('corrects the times of an already registered stoppage', function (): void {
    $event = paroCerrado();

    $corregido = $this->service->update($event, [
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'affects_production' => true,
        'started_at' => now()->startOfMonth()->setTime(8, 0),
        'ended_at' => now()->startOfMonth()->setTime(11, 30),
    ], $this->actor);

    expect($corregido->duration_minutes)->toBe(210)
        ->and(EquipmentDowntimeEvent::withoutGlobalScopes()->count())->toBe(1);
});

it('recomputes the duration instead of trusting what the form sends', function (): void {
    // Si la duración se aceptara del formulario, se podrían guardar horas que no
    // corresponden a las fechas y los indicadores mentirían sin que se note.
    $event = paroCerrado();

    $corregido = $this->service->update($event, [
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'affects_production' => true,
        'started_at' => now()->startOfMonth()->setTime(8, 0),
        'ended_at' => now()->startOfMonth()->setTime(9, 0),
        'duration_minutes' => 9999,
    ], $this->actor);

    expect($corregido->duration_minutes)->toBe(60);
});

it('re-derives the classification when the Tipo II changes', function (): void {
    $event = paroCerrado();

    $corregido = $this->service->update($event, [
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_reason' => StoppageReason::MantenimientoProgramado->value,
        'affects_production' => true,
        'started_at' => now()->startOfMonth()->setTime(8, 0),
        'ended_at' => now()->startOfMonth()->setTime(10, 0),
    ], $this->actor);

    expect($corregido->stoppage_category)->toBe(StoppageCategory::Planned)
        ->and($corregido->reported_type)->toBe(ReportedStoppageType::Scheduled)
        ->and($corregido->was_planned)->toBeTrue();
});

// ── La regla que protege las horas ───────────────────────────────────────────

it('refuses an edit that would make two stoppages overlap', function (): void {
    // Es la razón de que la edición pase por el servicio: sin esta comprobación,
    // mover una hora podría hacer que la misma hora perdida se cobrara dos veces
    // en los indicadores del mes.
    $primero = paroCerrado();
    $segundo = paroCerrado([
        'started_at' => now()->startOfMonth()->setTime(14, 0),
        'ended_at' => now()->startOfMonth()->setTime(16, 0),
    ]);

    $mover = fn () => $this->service->update($segundo, [
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'affects_production' => true,
        'started_at' => now()->startOfMonth()->setTime(9, 0),   // se mete dentro del primero
        'ended_at' => now()->startOfMonth()->setTime(11, 0),
    ], $this->actor);

    expect($mover)->toThrow(BusinessRuleException::class);

    // Y el paro original se queda como estaba: la transacción no deja a medias.
    expect($segundo->refresh()->started_at->format('H:i'))->toBe('14:00');
});

it('does not clash with itself when only the end time moves', function (): void {
    // El paro se solapa consigo mismo por definición: si no se excluyera de la
    // comprobación, ninguna corrección sería posible.
    $event = paroCerrado();

    $corregido = $this->service->update($event, [
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'affects_production' => true,
        'started_at' => now()->startOfMonth()->setTime(8, 0),
        'ended_at' => now()->startOfMonth()->setTime(12, 0),
    ], $this->actor);

    expect($corregido->duration_minutes)->toBe(240);
});

it('refuses an end before the start', function (): void {
    $event = paroCerrado();

    $invertido = fn () => $this->service->update($event, [
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'affects_production' => true,
        'started_at' => now()->startOfMonth()->setTime(10, 0),
        'ended_at' => now()->startOfMonth()->setTime(8, 0),
    ], $this->actor);

    expect($invertido)->toThrow(BusinessRuleException::class);
});

it('reopens a stoppage when the end time is cleared', function (): void {
    $event = paroCerrado();

    $corregido = $this->service->update($event, [
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'affects_production' => true,
        'started_at' => now()->startOfMonth()->setTime(8, 0),
        'ended_at' => null,
    ], $this->actor);

    expect($corregido->ended_at)->toBeNull()
        ->and($corregido->duration_minutes)->toBeNull();
});
