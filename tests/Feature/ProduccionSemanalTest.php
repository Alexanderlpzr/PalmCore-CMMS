<?php

use App\Domain\Analytics\Services\ProductionCalendarService;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\ProductionCalendar\Pages\CapturaSemanal;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/**
 * La semana es la forma en que el planificador teclea, no una entidad.
 *
 * Todo lo que se prueba aquí defiende esa frase: que por dentro se escriben días,
 * que una semana partida entre dos meses no descuadra el cierre mensual, y que un
 * día en blanco no se inventa como cero.
 */
beforeEach(function (): void {
    $this->service = app(ProductionCalendarService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->weekOf = function (string $anyDay): Carbon {
        return Carbon::parse($anyDay)->startOfWeek(Carbon::MONDAY);
    };

    $this->payload = function (Carbon $monday, float $hours = 22.0, float $tons = 300.0): array {
        $days = [];

        for ($date = $monday->copy(), $i = 0; $i < 7; $i++, $date->addDay()) {
            $days[$date->toDateString()] = [
                'programmed_hours' => $hours,
                'processed_tons' => $tons,
            ];
        }

        return $days;
    };
});

it('escribe los siete días de la semana de una sola vez', function (): void {
    $monday = ($this->weekOf)('2026-06-10');

    $result = $this->service->upsertWeek($this->plant, $monday, ($this->payload)($monday));

    expect($result['created'])->toBe(7)
        ->and($result['updated'])->toBe(0)
        ->and(ProductionCalendarDay::withoutGlobalScopes()
            ->where('plant_id', $this->plant->id)->count())->toBe(7);
});

it('actualiza la semana sin duplicar filas', function (): void {
    $monday = ($this->weekOf)('2026-06-10');

    $this->service->upsertWeek($this->plant, $monday, ($this->payload)($monday));
    $result = $this->service->upsertWeek($this->plant, $monday, ($this->payload)($monday, hours: 20.0, tons: 250.0));

    expect($result['created'])->toBe(0)
        ->and($result['updated'])->toBe(7)
        ->and(ProductionCalendarDay::withoutGlobalScopes()
            ->where('plant_id', $this->plant->id)->count())->toBe(7)
        ->and((float) ProductionCalendarDay::withoutGlobalScopes()
            ->where('plant_id', $this->plant->id)->sum('processed_tons'))->toBe(7 * 250.0);
});

it('reparte una semana a caballo entre dos meses en el mes que le toca a cada día', function (): void {
    // El cierre mensual es inmutable una vez congelado. Si una semana partida cargara
    // sus siete días en el mes donde empezó, el mes siguiente nacería descuadrado —es
    // el problema F2 del roadmap, y la razón de guardar días y no semanas.
    $monday = ($this->weekOf)('2026-07-01');

    $this->service->upsertWeek($this->plant, $monday, ($this->payload)($monday));

    $daysInJune = ProductionCalendarDay::withoutGlobalScopes()
        ->where('plant_id', $this->plant->id)
        ->whereBetween('calendar_date', ['2026-06-01', '2026-06-30'])
        ->count();

    $daysInJuly = ProductionCalendarDay::withoutGlobalScopes()
        ->where('plant_id', $this->plant->id)
        ->whereBetween('calendar_date', ['2026-07-01', '2026-07-31'])
        ->count();

    // La semana se parte de verdad —hay días en los dos meses— y cada mes solo suma
    // los suyos: es lo que mantiene auditable el cierre mensual.
    expect($daysInJune)->toBeGreaterThan(0)
        ->and($daysInJuly)->toBeGreaterThan(0)
        ->and($daysInJune + $daysInJuly)->toBe(7)
        ->and($this->service->programmedHours($this->plant, 2026, 6))->toBe($daysInJune * 22.0)
        ->and($this->service->programmedHours($this->plant, 2026, 7))->toBe($daysInJuly * 22.0);
});

it('no escribe el día que se deja en blanco', function (): void {
    // Cero y «no sé» no son lo mismo: un domingo en cero baja el denominador, un día
    // sin fila es un día del que no sabemos nada.
    $monday = ($this->weekOf)('2026-06-10');
    $days = ($this->payload)($monday);
    $days[$monday->copy()->addDays(2)->toDateString()]['programmed_hours'] = null;

    $result = $this->service->upsertWeek($this->plant, $monday, $days);

    expect($result['created'])->toBe(6)
        ->and($result['skipped'])->toBe(1)
        ->and(ProductionCalendarDay::withoutGlobalScopes()
            ->where('plant_id', $this->plant->id)->count())->toBe(6);
});

it('guarda el cero como dato legítimo', function (): void {
    $monday = ($this->weekOf)('2026-06-10');
    $days = ($this->payload)($monday);
    $sunday = $monday->copy()->addDays(6)->toDateString();
    $days[$sunday] = ['programmed_hours' => 0, 'processed_tons' => 0];

    $this->service->upsertWeek($this->plant, $monday, $days);

    expect(ProductionCalendarDay::withoutGlobalScopes()
        ->where('plant_id', $this->plant->id)
        ->where('calendar_date', $sunday)
        ->value('programmed_hours'))->toEqual(0.0);
});

it('rechaza un día que no pertenece a la semana', function (): void {
    $monday = ($this->weekOf)('2026-06-10');

    $this->service->upsertWeek($this->plant, $monday, [
        $monday->copy()->addDays(9)->toDateString() => ['programmed_hours' => 8, 'processed_tons' => 10],
    ]);
})->throws(BusinessRuleException::class);

it('rechaza una jornada de más de 24 horas', function (): void {
    $monday = ($this->weekOf)('2026-06-10');

    $this->service->upsertWeek($this->plant, $monday, [
        $monday->toDateString() => ['programmed_hours' => 25, 'processed_tons' => 0],
    ]);
})->throws(BusinessRuleException::class);

it('devuelve los siete días aunque no exista ninguna fila', function (): void {
    $monday = ($this->weekOf)('2026-06-10');

    $week = $this->service->week($this->plant, $monday);

    expect($week)->toHaveCount(7)
        ->and($week[$monday->toDateString()]['programmed_hours'])->toBeNull();
});

it('la planta guarda su semana desde la pantalla', function (): void {
    $this->seed(PermissionSeeder::class);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $admin = User::factory()->create(['is_active' => true]);
    $admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $admin->assignRole('administrador-general');

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $monday = ($this->weekOf)('2026-06-10');

    Livewire::test(CapturaSemanal::class)
        ->set('data.plant_id', $this->plant->id)
        ->set('data.week_of', $monday->toDateString())
        ->set('data.days', ($this->payload)($monday))
        ->call('save')
        ->assertHasNoErrors();

    expect(ProductionCalendarDay::withoutGlobalScopes()
        ->where('plant_id', $this->plant->id)->count())->toBe(7);
});

// ── El tope de unidad ────────────────────────────────────────────────────────

it('rechaza una jornada con toneladas en kilogramos', function (): void {
    // Pasó de verdad: un mes entero se cargó en kilos y entró sin protestar, inflando
    // mil veces la productividad y el kWh por tonelada.
    expect(fn () => app(ProductionCalendarService::class)->upsertWeek(
        plant: $this->plant,
        weekStart: Carbon::parse('2026-08-17'),
        days: ['2026-08-19' => ['programmed_hours' => 22, 'processed_tons' => 336040]],
    ))->toThrow(BusinessRuleException::class);
});

it('acepta una jornada de producción normal', function (): void {
    app(ProductionCalendarService::class)->upsertWeek(
        plant: $this->plant,
        weekStart: Carbon::parse('2026-08-17'),
        days: ['2026-08-19' => ['programmed_hours' => 22, 'processed_tons' => 336.04]],
    );

    expect(ProductionCalendarDay::withoutGlobalScopes()
        ->where('calendar_date', '2026-08-19')->first()->processed_tons)
        ->toBe(336.04);
});
