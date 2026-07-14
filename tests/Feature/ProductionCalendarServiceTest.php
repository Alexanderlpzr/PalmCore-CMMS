<?php

use App\Domain\Analytics\Services\ProductionCalendarService;
use App\Exceptions\BusinessRuleException;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use App\Models\Tenant;

/**
 * El calendario de producción es el denominador de la eficiencia de planta.
 *
 * Cargarlo a mano día por día es lo que hace que un CMMS se abandone. Cargarlo mal
 * es peor: un mes con horas programadas inventadas produce una eficiencia que nadie
 * puede defender.
 */
beforeEach(function (): void {
    $this->service = app(ProductionCalendarService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('programs a full month in one go', function (): void {
    $result = $this->service->programMonth($this->plant, 2026, 6, 22.0);

    expect($result['created'])->toBe(30)
        ->and($this->service->programmedHours($this->plant, 2026, 6))->toBe(660.0);
});

it('programs the rest days at zero instead of leaving them out', function (): void {
    // Cero y «no existe» no son lo mismo: el domingo programado en cero dice que la
    // planta no debía moler. Un domingo ausente diría que no sabemos nada de él.
    $this->service->programMonth($this->plant, 2026, 6, 22.0, restDays: [7]);

    $sundays = ProductionCalendarDay::withoutGlobalScopes()
        ->where('plant_id', $this->plant->id)
        ->where('programmed_hours', 0)
        ->count();

    expect($sundays)->toBe(4)
        ->and($this->service->programmedHours($this->plant, 2026, 6))->toBe(26 * 22.0);
});

it('respects a day the planner already corrected by hand', function (): void {
    ProductionCalendarDay::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'calendar_date' => '2026-06-15',
        'programmed_hours' => 8,
    ]);

    $result = $this->service->programMonth($this->plant, 2026, 6, 22.0);

    // Recargar el mes no puede borrar la corrección de quien sabía por qué la hizo.
    expect($result['created'])->toBe(29)
        ->and($result['skipped'])->toBe(1)
        ->and(ProductionCalendarDay::withoutGlobalScopes()
            ->where('calendar_date', '2026-06-15')->value('programmed_hours'))
        ->toEqual(8.0);
});

it('overwrites only when explicitly asked to', function (): void {
    $this->service->programMonth($this->plant, 2026, 6, 22.0);
    $result = $this->service->programMonth($this->plant, 2026, 6, 20.0, overwriteExisting: true);

    expect($result['updated'])->toBe(30)
        ->and($this->service->programmedHours($this->plant, 2026, 6))->toBe(600.0);
});

it('refuses a day with more hours than a day has', function (): void {
    expect(fn () => $this->service->programMonth($this->plant, 2026, 6, 26.0))
        ->toThrow(BusinessRuleException::class);
});

it('says nothing instead of zero when the month was never programmed', function (): void {
    // Un mes sin calendario no es un mes de cero horas: es un mes del que no sabemos
    // nada. La diferencia decide si la eficiencia se puede calcular o no existe.
    expect($this->service->programmedHours($this->plant, 2026, 6))->toBeNull();
});

it('never programs another tenant plant', function (): void {
    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);

    $this->service->programMonth($otherPlant, 2026, 6, 22.0);

    expect($this->service->programmedHours($this->plant, 2026, 6))->toBeNull()
        ->and(ProductionCalendarDay::withoutGlobalScopes()
            ->where('plant_id', $otherPlant->id)->first()->tenant_id)
        ->toBe($other->id);
});
