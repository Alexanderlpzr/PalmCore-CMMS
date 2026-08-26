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
