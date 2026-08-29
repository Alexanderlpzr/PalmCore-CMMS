<?php

use App\Domain\Energy\Services\EnergyMeterReadingService;
use App\Filament\Pages\Energia;
use App\Models\EnergyMeter;
use App\Models\EnergyMeterReading;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->user = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $this->red = EnergyMeter::factory()->grid()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id, 'code' => 'ENE-RED',
    ]);
    $this->turbina = EnergyMeter::factory()->turbine()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id, 'code' => 'ENE-TUR',
    ]);
});

it('guarda la ronda de todos los contadores de una vez', function (): void {
    Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-18')
        ->set("data.readings.{$this->red->id}.reading_value", 388_349)
        ->set("data.readings.{$this->turbina->id}.reading_value", 2_463_979)
        ->call('save')
        ->assertHasNoErrors();

    expect(EnergyMeterReading::count())->toBe(2);
});

it('deriva el consumo de la lectura anterior, sin que nadie lo teclee', function (): void {
    Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-18')
        ->set("data.readings.{$this->turbina->id}.reading_value", 2_519_653)
        ->call('save');

    Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-19')
        ->set("data.readings.{$this->turbina->id}.reading_value", 2_527_433)
        ->call('save');

    $lectura = EnergyMeterReading::where('energy_meter_id', $this->turbina->id)
        ->where('reading_date', '2026-08-19')
        ->first();

    expect($lectura->delta)->toBe(7780.0);
});

it('no escribe el contador que se dejó en blanco', function (): void {
    Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-18')
        ->set("data.readings.{$this->red->id}.reading_value", 388_349)
        ->set("data.readings.{$this->turbina->id}.reading_value", null)
        ->call('save');

    // Vacío no es cero: nadie pasó a leer la turbina ese día.
    expect(EnergyMeterReading::count())->toBe(1)
        ->and(EnergyMeterReading::where('energy_meter_id', $this->turbina->id)->exists())->toBeFalse();
});

it('corregir la lectura del mismo día no crea una segunda fila', function (): void {
    Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-18')
        ->set("data.readings.{$this->red->id}.reading_value", 388_000)
        ->call('save');

    Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-18')
        ->set("data.readings.{$this->red->id}.reading_value", 388_349)
        ->call('save');

    expect(EnergyMeterReading::where('energy_meter_id', $this->red->id)->count())->toBe(1)
        ->and(EnergyMeterReading::where('energy_meter_id', $this->red->id)->first()->reading_value)
        ->toBe(388_349.0);
});

it('precarga lo que ya se había anotado ese día', function (): void {
    Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-18')
        ->set("data.readings.{$this->red->id}.reading_value", 388_349)
        ->call('save')
        ->assertSet("data.readings.{$this->red->id}.reading_value", 388_349.0);
});

it('se la esconde a quien no tiene el permiso', function (): void {
    $ajeno = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $ajeno->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $this->actingAs($ajeno);

    expect(Energia::canAccess())->toBeFalse()
        ->and(Energia::shouldRegisterNavigation())->toBeFalse();
});

// ── El aviso del dígito de más ───────────────────────────────────────────────

it('no guarda una lectura implausible y avisa', function (): void {
    $service = app(EnergyMeterReadingService::class);
    $serie = [
        '2026-08-10' => 2_488_804, '2026-08-11' => 2_493_548, '2026-08-12' => 2_499_136,
        '2026-08-13' => 2_505_362, '2026-08-14' => 2_510_704, '2026-08-15' => 2_516_158,
    ];
    foreach ($serie as $f => $v) {
        $service->record($this->turbina, $v, $this->user, Carbon::parse($f));
    }

    $antes = EnergyMeterReading::count();

    Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-16')
        ->set("data.readings.{$this->turbina->id}.reading_value", 25_161_580)
        ->call('save');

    // Nada se escribió: el operario tiene que mirar el número otra vez.
    expect(EnergyMeterReading::count())->toBe($antes);
});

it('guarda la lectura rara cuando el operario la confirma', function (): void {
    $service = app(EnergyMeterReadingService::class);
    $serie = [
        '2026-08-10' => 2_488_804, '2026-08-11' => 2_493_548, '2026-08-12' => 2_499_136,
        '2026-08-13' => 2_505_362, '2026-08-14' => 2_510_704, '2026-08-15' => 2_516_158,
    ];
    foreach ($serie as $f => $v) {
        $service->record($this->turbina, $v, $this->user, Carbon::parse($f));
    }

    Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-16')
        ->set("data.readings.{$this->turbina->id}.reading_value", 25_161_580)
        ->set("data.readings.{$this->turbina->id}.force", true)
        ->call('save');

    expect(EnergyMeterReading::where('reading_date', '2026-08-16')->exists())->toBeTrue();
});

// ── La tabla del mes ─────────────────────────────────────────────────────────

it('muestra el mes de lecturas debajo de la ronda', function (): void {
    $service = app(EnergyMeterReadingService::class);
    // La serie real de la turbina en agosto.
    foreach ([
        '2026-07-31' => 2_463_979, '2026-08-18' => 2_519_653, '2026-08-19' => 2_527_433,
    ] as $f => $v) {
        $service->record($this->turbina, $v, $this->user, Carbon::parse($f));
    }

    Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-19')
        ->assertSee('Lecturas de Agosto de 2026')
        ->assertSee('TOTAL DEL MES')
        // El consumo del 19: 2.527.433 − 2.519.653.
        ->assertSee('7.780')
        // Y el total del mes es la suma de los deltas, no la resta de los extremos.
        ->assertSee('63.454');
});

it('deja los días sin leer en guion, no en cero', function (): void {
    app(EnergyMeterReadingService::class)
        ->record($this->turbina, 2_463_979, $this->user, Carbon::parse('2026-08-19'));

    $datos = Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-19')
        ->instance()
        ->monthTable();

    $dia18 = collect($datos['days'])->firstWhere('date', '2026-08-18');

    // Nadie pasó a leer el 18. Cero afirmaría que el contador no se movió.
    expect($dia18['cells'][$this->turbina->id]['delta'])->toBeNull()
        ->and($dia18['cells'][$this->turbina->id]['accumulated'])->toBeNull();
});

it('marca el día en que se reemplazó el contador', function (): void {
    $service = app(EnergyMeterReadingService::class);
    $service->record($this->red, 388_349, $this->user, Carbon::parse('2026-08-18'));
    $service->record($this->red, 120, $this->user, Carbon::parse('2026-08-19'));

    $datos = Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-19')
        ->instance()
        ->monthTable();

    $dia19 = collect($datos['days'])->firstWhere('date', '2026-08-19');

    expect($dia19['cells'][$this->red->id]['is_reset'])->toBeTrue();
});

it('lleva la ronda al día que se pulsa en la tabla', function (): void {
    app(EnergyMeterReadingService::class)
        ->record($this->red, 388_349, $this->user, Carbon::parse('2026-08-10'));

    Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-19')
        ->call('goToDay', '2026-08-10')
        ->assertSet('data.reading_date', '2026-08-10')
        // Y precarga lo que ya estaba anotado ese día.
        ->assertSet("data.readings.{$this->red->id}.reading_value", 388_349.0);
});

it('muestra el mes de la fecha elegida, no siempre el actual', function (): void {
    $datos = Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-07-15')
        ->instance()
        ->monthTable();

    expect($datos['monthLabel'])->toContain('Julio')
        ->and(collect($datos['days'])->first()['date'])->toBe('2026-07-01')
        ->and(collect($datos['days'])->last()['date'])->toBe('2026-07-31');
});

it('no pinta días que todavía no han ocurrido', function (): void {
    // Un contador no se lee por adelantado: las filas futuras solo alargan la tabla.
    $datos = Livewire::test(Energia::class)
        ->set('data.reading_date', now()->startOfMonth()->toDateString())
        ->instance()
        ->monthTable();

    expect(collect($datos['days'])->last()['date'])->toBe(now()->toDateString());
});

// ── El calendario del mes ────────────────────────────────────────────────────

it('trae el mes entero, con el día 1 bajo su día de la semana', function (): void {
    $cal = Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-19')
        ->instance()
        ->monthCalendar();

    // Agosto de 2026 empieza en sábado: cinco casillas en blanco antes del 1.
    expect($cal['days'])->toHaveCount(31)
        ->and($cal['offset'])->toBe(5)
        ->and($cal['days'][0]['date'])->toBe('2026-08-01')
        ->and($cal['monthLabel'])->toContain('Agosto');
});

it('marca el día que tiene lectura y deja el hueco a la vista', function (): void {
    app(EnergyMeterReadingService::class)
        ->record($this->red, 388_349, $this->user, Carbon::parse('2026-08-10'));

    $cal = Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-19')
        ->instance()
        ->monthCalendar();

    $porFecha = collect($cal['days'])->keyBy('date');

    // El hueco es lo que se busca al volver de unos días fuera: sin él, ponerse al día
    // obliga a bajar a la tabla y leer treinta filas buscando guiones.
    expect($porFecha['2026-08-10']['has_data'])->toBeTrue()
        ->and($porFecha['2026-08-11']['has_data'])->toBeFalse();
});

it('cuenta cuántos días quedan por anotar', function (): void {
    $cal = Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-19')
        ->instance()
        ->monthCalendar();

    // Ni una sola lectura: faltan todos los días que ya ocurrieron.
    $ocurridos = collect($cal['days'])->reject(fn (array $d): bool => $d['is_future'])->count();

    expect($cal['legend'])->toContain((string) $ocurridos);
});

it('apaga los días que todavía no han ocurrido', function (): void {
    $siguiente = Carbon::today()->addMonthNoOverflow()->startOfMonth();

    $cal = Livewire::test(Energia::class)
        ->set('data.reading_date', $siguiente->toDateString())
        ->instance()
        ->monthCalendar();

    // Un contador no se lee por adelantado: el mes que viene está entero apagado.
    expect(collect($cal['days'])->every(fn (array $d): bool => $d['is_future']))->toBeTrue()
        ->and($cal['legend'])->toContain('Todos los días');
});

it('resalta el día que está cargado en la ronda', function (): void {
    $cal = Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-19')
        ->instance()
        ->monthCalendar();

    expect(collect($cal['days'])->firstWhere('is_selected', true)['date'])->toBe('2026-08-19');
});

it('el calendario sigue a la fecha elegida, no al mes actual', function (): void {
    $cal = Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-07-15')
        ->instance()
        ->monthCalendar();

    // Julio de 2026 empieza en miércoles.
    expect($cal['monthLabel'])->toContain('Julio')
        ->and($cal['offset'])->toBe(2)
        ->and($cal['days'])->toHaveCount(31);
});

it('no pinta treinta filas de guiones cuando el mes no tiene ninguna lectura', function (): void {
    // El mes genera sus días aunque nadie haya leído nada, y la tabla los pintaba todos
    // vacíos: treinta filas que no dicen más que la frase que ahora las reemplaza.
    Livewire::test(Energia::class)
        ->set('data.reading_date', '2026-08-19')
        ->assertSee('todavía no tiene ninguna lectura anotada')
        ->assertDontSee('TOTAL DEL MES');
});
