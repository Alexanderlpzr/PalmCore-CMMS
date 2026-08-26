<?php

use App\Domain\Analytics\Services\ProductionCalendarService;
use App\Exceptions\BusinessRuleException;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Ninguna puerta deja entrar kilogramos donde se esperan toneladas.
 *
 * Un mes entero se cargó así —196.350 en un día que la planta prensa en unas 250— y la
 * productividad quedó mil veces inflada durante semanas sin que nada chirriara. El
 * primer arreglo puso el límite en el servicio de la rejilla semanal, y eso resultó
 * insuficiente: otras tres vías escriben la misma columna sin pasar por ahí.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('la rejilla semanal rechaza kilogramos', function (): void {
    expect(fn () => app(ProductionCalendarService::class)->upsertWeek(
        plant: $this->plant,
        weekStart: Carbon::parse('2026-08-17'),
        days: ['2026-08-19' => ['programmed_hours' => 22, 'processed_tons' => 336040]],
    ))->toThrow(BusinessRuleException::class);
});

it('la base rechaza kilogramos aunque nadie los valide antes', function (): void {
    // Escritura directa al modelo: el camino del formulario del día, de la edición en
    // línea, de un seeder o de la consola. Si el único guardia estuviera en el servicio,
    // esto entraría.
    expect(fn () => ProductionCalendarDay::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'calendar_date' => '2026-08-19',
        'programmed_hours' => 22,
        'processed_tons' => 336040,
    ]))->toThrow(QueryException::class);
});

it('la base rechaza una cifra negativa', function (): void {
    expect(fn () => ProductionCalendarDay::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'calendar_date' => '2026-08-20',
        'programmed_hours' => 22,
        'processed_tons' => -5,
    ]))->toThrow(QueryException::class);
});

it('deja pasar una jornada real', function (): void {
    $dia = ProductionCalendarDay::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'calendar_date' => '2026-08-19',
        'programmed_hours' => 22,
        'processed_tons' => 336.04,
    ]);

    expect($dia->processed_tons)->toBe(336.04);
});

it('deja pasar un día sin molienda', function (): void {
    // Cero es un dato legítimo: un domingo que nunca debía producir.
    $dia = ProductionCalendarDay::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'calendar_date' => '2026-08-23',
        'programmed_hours' => 0,
        'processed_tons' => 0,
    ]);

    expect($dia->processed_tons)->toBe(0.0);
});
