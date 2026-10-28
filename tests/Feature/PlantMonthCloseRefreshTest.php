<?php

use App\Domain\Analytics\Services\PlantKpiService;
use App\Jobs\RefreshPlantMonthJob;
use App\Models\EnergyMeter;
use App\Models\EnergyMeterReading;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\ProductionCalendarDay;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/*
 * El cierre del mes de una planta se rehace apenas cambian sus días de producción o sus
 * lecturas de energía, sin esperar a la corrida de las 04:00: así «Consumo de energía»
 * no se queda con las toneladas de la víspera mientras «Producción» ya las suma.
 *
 * Hoy es el 28 de octubre de 2026.
 */

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-28 09:30', 'America/Bogota'));
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
});

function diaDeProduccion(Plant $plant, string $fecha, float $toneladas): ProductionCalendarDay
{
    return ProductionCalendarDay::factory()->create([
        'tenant_id' => $plant->tenant_id,
        'plant_id' => $plant->id,
        'calendar_date' => $fecha,
        'programmed_hours' => 20,
        'processed_tons' => $toneladas,
    ]);
}

it('queues the close of the month when a production day is saved, once per plant and month', function (): void {
    Queue::fake();

    diaDeProduccion($this->plant, '2026-10-21', 243.25);
    diaDeProduccion($this->plant, '2026-10-22', 300.41);

    Queue::assertPushed(RefreshPlantMonthJob::class, fn (RefreshPlantMonthJob $job): bool => $job->plantId === $this->plant->id && $job->year === 2026 && $job->month === 10 && $job->delay !== null);
    // Único por planta y mes: los demás días del mismo mes no encolan otro.
    expect((new RefreshPlantMonthJob($this->plant->id, 2026, 10))->uniqueId())->toBe("{$this->plant->id}|2026|10");
});

it('leaves alone the months that management already closed', function (): void {
    Queue::fake();

    diaDeProduccion($this->plant, '2026-07-15', 250);

    Queue::assertNotPushed(RefreshPlantMonthJob::class);
});

it('queues the close of the month of the plant of an energy reading', function (): void {
    Queue::fake();

    $medidor = EnergyMeter::factory()->create(['tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id]);
    // Sin la fábrica de lecturas: crea un usuario con una columna que «users» ya no tiene.
    EnergyMeterReading::query()->create([
        'tenant_id' => $this->tenant->id,
        'energy_meter_id' => $medidor->id,
        'reading_date' => '2026-09-30',
        'reading_value' => 100_000,
        'delta' => 0,
        'accumulated_value' => 0,
        'is_reset' => false,
        'recorded_by' => User::factory()->create()->id,
    ]);

    Queue::assertPushed(RefreshPlantMonthJob::class, fn (RefreshPlantMonthJob $job): bool => $job->plantId === $this->plant->id && $job->month === 9);
});

it('brings the tons of the month to what production says', function (): void {
    // El cierre se hizo con el calendario hasta el 20.
    diaDeProduccion($this->plant, '2026-10-20', 300);
    (new RefreshPlantMonthJob($this->plant->id, 2026, 10))->handle(app(PlantKpiService::class));

    // Llegan tarde los días del 21 al 27.
    diaDeProduccion($this->plant, '2026-10-21', 243.25);
    diaDeProduccion($this->plant, '2026-10-22', 300.41);
    (new RefreshPlantMonthJob($this->plant->id, 2026, 10))->handle(app(PlantKpiService::class));

    $cierre = PlantMonthlyKpi::withoutGlobalScopes()->where('plant_id', $this->plant->id)->where('year', 2026)->where('month', 10)->sole();

    expect((float) $cierre->processed_tons)->toBe(843.66);
});
