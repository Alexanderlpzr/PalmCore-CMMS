<?php

use App\Domain\Assets\Enums\EquipmentDowntimeCauseType;
use App\Domain\Assets\Enums\PlantSection;
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

// ── Paros sin OT: el 70% de la operación real ────────────────────────────────

it('registers a plant-wide stoppage with no equipment and no work order', function (): void {
    // Falta de fruta: la planta para, ningún equipo falló, nadie abre una OT.
    $event = $this->service->register([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'stoppage_category' => StoppageCategory::RawMaterial,
        'stoppage_cause' => 'No llegó fruta de proveedor',
        'started_at' => now()->subHours(3),
        'ended_at' => now()->subHours(1),
    ], $this->actor);

    expect($event->equipment_id)->toBeNull()
        ->and($event->work_order_id)->toBeNull()
        ->and($event->isPlantWide())->toBeTrue()
        ->and($event->plant_id)->toBe($this->plant->id)
        ->and($event->duration_minutes)->toBe(120)
        ->and($event->stoppage_category)->toBe(StoppageCategory::RawMaterial)
        ->and($event->stoppage_cause)->toBe('No llegó fruta de proveedor')
        ->and($event->registered_by)->toBe($this->actor->id)
        ->and($event->source)->toBe('manual');
});

it('registra el paro por Tipo II, derivando categoría física, Tipo I y sección', function (): void {
    $event = $this->service->register([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'section' => PlantSection::Extraccion->value,
        'stoppage_reason' => StoppageReason::FallaMecanica->value,
        'stoppage_cause' => 'Se rompió la cadena del elevador',
        'started_at' => now()->subHours(2),
        'ended_at' => now()->subHour(),
    ], $this->actor);

    expect($event->stoppage_reason)->toBe(StoppageReason::FallaMecanica)
        ->and($event->stoppage_category)->toBe(StoppageCategory::Mechanical) // derivada del Tipo II
        ->and($event->reported_type)->toBe(ReportedStoppageType::Maintenance) // Tipo I del Tipo II
        ->and($event->section)->toBe(PlantSection::Extraccion)
        ->and($event->cause_type)->toBe(EquipmentDowntimeCauseType::Corrective)
        ->and($event->duration_minutes)->toBe(60);
});

it('un Tipo II operativo (atascamiento) no cuenta como falla de mantenimiento', function (): void {
    $event = $this->service->register([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'section' => PlantSection::Esterilizacion->value,
        'stoppage_reason' => StoppageReason::Atascamiento->value,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
    ], $this->actor);

    expect($event->stoppage_category)->toBe(StoppageCategory::Process)
        ->and($event->reported_type)->toBe(ReportedStoppageType::Operational)
        ->and($event->stoppage_category->isMaintenanceResponsibility())->toBeFalse();
});

it('un paro de falla de mantenimiento marca la última falla del equipo', function (): void {
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'last_failure_at' => null,
    ]);
    $started = now()->subHours(2);

    $this->service->register([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'stoppage_reason' => StoppageReason::FallaMecanica->value,
        'started_at' => $started,
        'ended_at' => now()->subHour(),
    ], $this->actor);

    // Antes lo marcaba la OT; ahora lo marca el registro manual del paro.
    expect($equipment->fresh()->last_failure_at?->timestamp)->toBe($started->timestamp);
});

it('un paro operativo no marca la última falla (no es de mantenimiento)', function (): void {
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'last_failure_at' => null,
    ]);

    $this->service->register([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'stoppage_reason' => StoppageReason::Atascamiento->value,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
    ], $this->actor);

    expect($equipment->fresh()->last_failure_at)->toBeNull();
});

it('derives the plant from the equipment when only the equipment is given', function (): void {
    $event = $this->service->start([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'stoppage_cause' => 'Rodamiento prensa 2',
    ], $this->actor);

    expect($event->plant_id)->toBe($this->plant->id)
        ->and($event->cause_type)->toBe(EquipmentDowntimeCauseType::Corrective)
        ->and($event->was_planned)->toBeFalse()
        ->and($event->isOngoing())->toBeTrue()
        ->and($event->duration_minutes)->toBeNull();
});

it('refuses a stoppage that names neither an equipment nor a plant', function (): void {
    expect(fn () => $this->service->start([
        'tenant_id' => $this->tenant->id,
        'stoppage_category' => StoppageCategory::Process,
    ], $this->actor))->toThrow(BusinessRuleException::class);
});

it('marks a planned stoppage as planned', function (): void {
    $event = $this->service->start([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Planned,
    ], $this->actor);

    expect($event->was_planned)->toBeTrue()
        ->and($event->cause_type)->toBe(EquipmentDowntimeCauseType::Preventive);
});

// ── Ciclo de vida ─────────────────────────────────────────────────────────────

it('closes an open stoppage and computes its duration', function (): void {
    $event = $this->service->start([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Electrical,
        'started_at' => now()->subMinutes(90),
    ], $this->actor);

    $event = $this->service->end($event, now());

    expect($event->isOngoing())->toBeFalse()
        ->and($event->duration_minutes)->toBe(90);
});

it('refuses to close a stoppage twice', function (): void {
    $event = $this->service->register([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'started_at' => now()->subHours(2),
        'ended_at' => now()->subHour(),
    ], $this->actor);

    expect(fn () => $this->service->end($event))->toThrow(BusinessRuleException::class);
});

it('refuses a stoppage that ends before it starts', function (): void {
    expect(fn () => $this->service->register([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'started_at' => now(),
        'ended_at' => now()->subHour(),
    ], $this->actor))->toThrow(BusinessRuleException::class);
});

it('refuses to open a second stoppage on an equipment already down', function (): void {
    $this->service->start([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
    ], $this->actor);

    expect(fn () => $this->service->start([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Electrical,
    ], $this->actor))->toThrow(BusinessRuleException::class);
});

it('lets an equipment stoppage and a plant stoppage coexist', function (): void {
    // La prensa está en mantenimiento mientras además falta fruta: dos hechos
    // distintos, ambos ciertos al mismo tiempo.
    $this->service->start([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
    ], $this->actor);

    $plantWide = $this->service->start([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'stoppage_category' => StoppageCategory::RawMaterial,
    ], $this->actor);

    expect($plantWide->exists)->toBeTrue()
        ->and($this->service->ongoingForPlant($this->plant))->toHaveCount(2)
        ->and($this->service->ongoingFor($this->equipment))->not->toBeNull();
});

// ── Lo que el gerente pregunta cada lunes ────────────────────────────────────

it('splits lost production hours by Tipo I', function (): void {
    $from = now()->startOfMonth();

    $this->service->register([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'stoppage_category' => StoppageCategory::RawMaterial,
        'started_at' => now()->subDays(3),
        'ended_at' => now()->subDays(3)->addHours(4),
    ], $this->actor);

    $this->service->register([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'started_at' => now()->subDays(2),
        'ended_at' => now()->subDays(2)->addMinutes(90),
    ], $this->actor);

    $this->service->register([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'started_at' => now()->subDay(),
        'ended_at' => now()->subDay()->addMinutes(30),
    ], $this->actor);

    $hours = $this->service->lostHoursByCategory($this->plant->id, $from, now());

    // Peor primero: es el orden en que se lee un Pareto.
    expect($hours)->toBe([
        'raw_material' => 4.0,
        'mechanical' => 2.0,
    ]);
});

it('excludes stoppages that did not cost production hours', function (): void {
    $this->service->register([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'affects_production' => false,
        'started_at' => now()->subHours(5),
        'ended_at' => now()->subHours(3),
    ], $this->actor);

    expect($this->service->lostHoursByCategory($this->plant->id, now()->subMonth(), now()))->toBe([]);
});

it('separates what maintenance owns from what it merely suffers', function (): void {
    expect(StoppageCategory::Mechanical->isMaintenanceResponsibility())->toBeTrue()
        ->and(StoppageCategory::Electrical->isMaintenanceResponsibility())->toBeTrue()
        ->and(StoppageCategory::RawMaterial->isMaintenanceResponsibility())->toBeFalse()
        ->and(StoppageCategory::Utilities->isMaintenanceResponsibility())->toBeFalse();
});

// ── Multi-tenant ──────────────────────────────────────────────────────────────

it('never counts another tenant stoppages', function (): void {
    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);

    $this->service->register([
        'tenant_id' => $other->id,
        'plant_id' => $otherPlant->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'started_at' => now()->subHours(4),
        'ended_at' => now()->subHours(2),
    ], $this->actor);

    expect($this->service->lostHoursByCategory($this->plant->id, now()->subMonth(), now()))->toBe([])
        ->and(EquipmentDowntimeEvent::withoutGlobalScopes()->where('tenant_id', $other->id)->count())->toBe(1);
});
