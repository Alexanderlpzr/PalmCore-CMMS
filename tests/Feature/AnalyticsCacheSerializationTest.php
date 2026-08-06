<?php

use App\Domain\Analytics\DTOs\TrendPoint;
use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/*
 * `config/cache.php` fija `serializable_classes => false`: el framework
 * deserializa con `allowed_classes: false`, así que NINGÚN objeto PHP sobrevive
 * a la caché — vuelve como `__PHP_Incomplete_Class`. Es una protección real
 * contra cadenas de gadgets si se filtra la APP_KEY, y no se toca.
 *
 * Guardar los DTO ahí dentro tumbó cuatro gráficas del Dashboard con error 500
 * en producción, y los 1542 tests pasaron igual: `phpunit.xml` usa el driver
 * `array`, que no serializa nada, así que el problema no existe bajo tests.
 *
 * Por eso este archivo fuerza el driver `database` — el mismo de producción —
 * antes de cada prueba. Sin esa línea, estos tests pasarían aunque el bug
 * volviera entero.
 */
beforeEach(function (): void {
    config()->set('cache.default', 'database');
    Cache::flush();

    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);

    // Un paro cerrado: sin datos, los servicios devuelven arrays vacíos y la
    // caché nunca llega a guardar un objeto — que es justamente por lo que el
    // fallo estuvo latente hasta que la planta tuvo actividad de verdad.
    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_category' => StoppageCategory::Mechanical->value,
        'reported_type' => ReportedStoppageType::Maintenance->value,
        'affects_production' => true,
        'was_planned' => false,
        'started_at' => now()->subDays(3)->setTime(8, 0),
        'ended_at' => now()->subDays(3)->setTime(10, 0),
        'duration_minutes' => 120,
    ]);

    $this->service = app(AnalyticsService::class);
});

/** Todo lo que devuelve TrendPoint[] tiene que sobrevivir a la segunda lectura. */
it('returns real objects on the cached read, not incomplete ones', function (string $method): void {
    // Primera llamada: calcula y guarda. Segunda: lee de la caché — es ahí donde
    // el objeto volvía roto y la gráfica reventaba con un 500.
    $this->service->{$method}($this->tenant->id);
    $points = $this->service->{$method}($this->tenant->id);

    expect($points)->toBeArray();

    foreach ($points as $point) {
        expect($point)->toBeInstanceOf(TrendPoint::class)
            ->and($point->label)->toBeString();
    }
})->with([
    'downtimeByReportedType',
    'downtimeByStoppageCategory',
    'downtimeBySection',
    'downtimeByReason',
    'failuresByMonth',
    'downtimeTrend',
    'costByEquipment',
    'paretoFailures',
    'paretoFailuresByMode',
]);

it('keeps the two lists of the reliability ranking usable after caching', function (): void {
    $this->service->reliabilityRanking($this->tenant->id);
    $ranking = $this->service->reliabilityRanking($this->tenant->id);

    expect($ranking)->toHaveKeys(['best', 'worst']);

    foreach ([...$ranking['best'], ...$ranking['worst']] as $point) {
        expect($point)->toBeInstanceOf(TrendPoint::class);
    }
});

it('reads the same values from cache as it computed the first time', function (): void {
    $fresh = $this->service->downtimeByStoppageCategory($this->tenant->id);
    $cached = $this->service->downtimeByStoppageCategory($this->tenant->id);

    // Que sean objetos no basta: tienen que traer los mismos números.
    expect($cached)->not->toBeEmpty()
        ->and(array_map(fn (TrendPoint $p): string => $p->label, $cached))
        ->toBe(array_map(fn (TrendPoint $p): string => $p->label, $fresh))
        ->and(array_map(fn (TrendPoint $p): ?float => $p->value, $cached))
        ->toBe(array_map(fn (TrendPoint $p): ?float => $p->value, $fresh));
});

it('never puts a PHP object into the cache store', function (): void {
    // La comprobación de raíz: si algún día alguien vuelve a guardar un objeto,
    // esto lo caza aunque el getter siga funcionando por casualidad.
    $this->service->downtimeByStoppageCategory($this->tenant->id);
    $this->service->costByEquipment($this->tenant->id);
    $this->service->failuresByMonth($this->tenant->id);

    $rows = DB::table('cache')->where('key', 'like', '%analytics%')->pluck('value');

    expect($rows)->not->toBeEmpty();

    foreach ($rows as $raw) {
        expect($raw)->not->toMatch('/O:\d+:"/');
    }
});
