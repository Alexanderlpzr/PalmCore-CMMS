<?php

use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Services\DowntimeService;
use App\Exceptions\BusinessRuleException;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * F1 y F2 — la eficiencia de planta va a gerencia, y tiene que poder auditarse
 * hasta el evento que la originó.
 *
 * Dos paros que se pisan no pueden cobrar dos veces la misma hora, y un paro que
 * cruza la medianoche del día 1 no puede cargarle a un mes horas del otro.
 */
beforeEach(function (): void {
    $this->downtime = app(DowntimeService::class);
    $this->kpis = app(PlantKpiService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);
    $this->actor = User::factory()->create();
});

/** @param  array<string, mixed>  $overrides */
function paro(array $overrides = []): array
{
    return [
        'tenant_id' => test()->tenant->id,
        'plant_id' => test()->plant->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        ...$overrides,
    ];
}

// ── F1: el mismo activo no puede estar parado dos veces ──────────────────────

it('refuses a stoppage that overlaps a closed one on the same equipment', function (): void {
    $this->downtime->register(paro([
        'equipment_id' => $this->equipment->id,
        'started_at' => '2026-06-10 08:00:00',
        'ended_at' => '2026-06-10 12:00:00',
    ]), $this->actor);

    // Empieza dentro del anterior: las 2 h compartidas se contarían dos veces.
    $this->downtime->register(paro([
        'equipment_id' => $this->equipment->id,
        'started_at' => '2026-06-10 10:00:00',
        'ended_at' => '2026-06-10 14:00:00',
    ]), $this->actor);
})->throws(BusinessRuleException::class, 'se cruza con este');

it('refuses a plant-wide stoppage that overlaps another plant-wide one', function (): void {
    $this->downtime->register(paro([
        'started_at' => '2026-06-10 08:00:00',
        'ended_at' => '2026-06-10 12:00:00',
    ]), $this->actor);

    $this->downtime->register(paro([
        'stoppage_category' => StoppageCategory::RawMaterial,
        'started_at' => '2026-06-10 11:00:00',
        'ended_at' => '2026-06-10 13:00:00',
    ]), $this->actor);
})->throws(BusinessRuleException::class);

it('accepts a stoppage that starts exactly when the previous one ended', function (): void {
    $this->downtime->register(paro([
        'equipment_id' => $this->equipment->id,
        'started_at' => '2026-06-10 08:00:00',
        'ended_at' => '2026-06-10 12:00:00',
    ]), $this->actor);

    $this->downtime->register(paro([
        'equipment_id' => $this->equipment->id,
        'started_at' => '2026-06-10 12:00:00',
        'ended_at' => '2026-06-10 13:00:00',
    ]), $this->actor);

    expect(EquipmentDowntimeEvent::withoutGlobalScopes()->count())->toBe(2);
});

it('lets an equipment stoppage coexist with a plant-wide one', function (): void {
    // Corte de energía mientras se repara una bomba: son dos hechos distintos.
    $this->downtime->register(paro([
        'stoppage_category' => StoppageCategory::Utilities,
        'started_at' => '2026-06-10 08:00:00',
        'ended_at' => '2026-06-10 12:00:00',
    ]), $this->actor);

    $this->downtime->register(paro([
        'equipment_id' => $this->equipment->id,
        'started_at' => '2026-06-10 09:00:00',
        'ended_at' => '2026-06-10 11:00:00',
    ]), $this->actor);

    expect(EquipmentDowntimeEvent::withoutGlobalScopes()->count())->toBe(2);
});

it('refuses to close an open stoppage over a later one', function (): void {
    $open = $this->downtime->start(paro([
        'equipment_id' => $this->equipment->id,
        'started_at' => '2026-06-10 08:00:00',
    ]), $this->actor);

    // Un paro abierto llega hasta el infinito: nada puede registrarse después de él.
    expect(fn () => $this->downtime->register(paro([
        'equipment_id' => $this->equipment->id,
        'started_at' => '2026-06-11 08:00:00',
        'ended_at' => '2026-06-11 10:00:00',
    ]), $this->actor))->toThrow(BusinessRuleException::class);

    expect($this->downtime->end($open, '2026-06-10 12:00:00')->ended_at->format('H:i'))->toBe('12:00');
});

it('makes an overlap impossible to write even behind the service', function (): void {
    $this->downtime->register(paro([
        'equipment_id' => $this->equipment->id,
        'started_at' => '2026-06-10 08:00:00',
        'ended_at' => '2026-06-10 12:00:00',
    ]), $this->actor);

    // La regla no vive solo en PHP: un seeder, un job o un INSERT a mano tampoco pueden.
    expect(fn () => EquipmentDowntimeEvent::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'started_at' => '2026-06-10 09:00:00',
        'ended_at' => '2026-06-10 10:00:00',
        'cause_type' => 'corrective',
        'stoppage_category' => StoppageCategory::Mechanical->value,
    ]))->toThrow(QueryException::class);
});

it('keeps the overlap rule inside the tenant', function (): void {
    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);

    $this->downtime->register(paro([
        'started_at' => '2026-06-10 08:00:00',
        'ended_at' => '2026-06-10 12:00:00',
    ]), $this->actor);

    // La misma hora, otra empresa, otra planta: nada que ver.
    $this->downtime->register([
        'tenant_id' => $other->id,
        'plant_id' => $otherPlant->id,
        'stoppage_category' => StoppageCategory::Mechanical,
        'started_at' => '2026-06-10 08:00:00',
        'ended_at' => '2026-06-10 12:00:00',
    ], $this->actor);

    expect(EquipmentDowntimeEvent::withoutGlobalScopes()->count())->toBe(2);
});

// ── F1: la planta tiene un solo reloj ────────────────────────────────────────

it('counts two simultaneous stoppages of different equipment as the hours the plant lost, once', function (): void {
    $other = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);

    programme($this->plant, '2026-06-10', 24);

    // Prensa parada 08:00–12:00 y caldera 10:00–14:00: la planta perdió de 08 a 14.
    $this->downtime->register(paro([
        'equipment_id' => $this->equipment->id,
        'started_at' => '2026-06-10 08:00:00',
        'ended_at' => '2026-06-10 12:00:00',
    ]), $this->actor);

    $this->downtime->register(paro([
        'equipment_id' => $other->id,
        'started_at' => '2026-06-10 10:00:00',
        'ended_at' => '2026-06-10 14:00:00',
    ]), $this->actor);

    $lost = $this->kpis->lostHours(
        $this->plant,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30 23:59:59'),
    );

    // Sumar duraciones daría 8. La planta estuvo parada 6.
    expect($lost)->toBe(6.0);
});

// ── F2: un paro entre dos meses le pertenece a los dos ───────────────────────

it('splits a stoppage that straddles the month boundary between both months', function (): void {
    programme($this->plant, '2026-06-30', 24);
    programme($this->plant, '2026-07-01', 24);

    // 31 de mayo… perdón: del 30/06 22:00 al 01/07 03:00. Dos horas de junio, tres de julio.
    $this->downtime->register(paro([
        'started_at' => '2026-06-30 22:00:00',
        'ended_at' => '2026-07-01 03:00:00',
    ]), $this->actor);

    $june = $this->kpis->calculate(
        $this->plant,
        Carbon::parse('2026-06-01 00:00:00'),
        Carbon::parse('2026-06-30 23:59:59'),
    );

    $july = $this->kpis->calculate(
        $this->plant,
        Carbon::parse('2026-07-01 00:00:00'),
        Carbon::parse('2026-07-31 23:59:59'),
    );

    expect($june['lost_hours'])->toBe(2.0)
        ->and($july['lost_hours'])->toBe(3.0);
});

it('charges july nothing for a paro that ended before july', function (): void {
    programme($this->plant, '2026-06-15', 24);

    $this->downtime->register(paro([
        'started_at' => '2026-06-15 08:00:00',
        'ended_at' => '2026-06-15 10:00:00',
    ]), $this->actor);

    expect($this->kpis->lostHours(
        $this->plant,
        Carbon::parse('2026-07-01'),
        Carbon::parse('2026-07-31 23:59:59'),
    ))->toBe(0.0);
});

it('splits the Tipo I Pareto across the month boundary too', function (): void {
    $this->downtime->register(paro([
        'stoppage_category' => StoppageCategory::RawMaterial,
        'started_at' => '2026-06-30 20:00:00',
        'ended_at' => '2026-07-01 04:00:00',
    ]), $this->actor);

    $june = $this->downtime->lostHoursByCategory(
        $this->plant->id,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30 23:59:59'),
    );

    expect($june)->toBe(['raw_material' => 4.0]);
});

/** El planificador programó `$hours` horas ese día. */
function programme(Plant $plant, string $date, float $hours): void
{
    ProductionCalendarDay::withoutGlobalScopes()->create([
        'tenant_id' => $plant->tenant_id,
        'plant_id' => $plant->id,
        'calendar_date' => $date,
        'programmed_hours' => $hours,
    ]);
}

it('does not let a forgotten open paro poison the month', function (): void {
    $this->downtime->start(paro(['started_at' => '2026-06-10 08:00:00']), $this->actor);

    // Un paro abierto todavía no costó un número de horas: no se inventa uno.
    expect(DB::table('equipment_downtime_events')->whereNull('ended_at')->count())->toBe(1)
        ->and($this->kpis->lostHours(
            $this->plant,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-30 23:59:59'),
        ))->toBe(0.0);
});
