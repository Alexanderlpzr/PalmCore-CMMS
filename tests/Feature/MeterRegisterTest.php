<?php

use App\Filament\Resources\MeterReadings\Pages\ListMeterReadings;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

// ── El hub y sus pestañas ────────────────────────────────────────────────────

it('el centro de horómetros arranca en la pestaña diaria y muestra solo equipos diarios', function (): void {
    Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily', 'code' => 'DAILY-1']);
    Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'weekly', 'code' => 'WEEKLY-1']);

    Livewire::test(ListMeterReadings::class)
        ->assertOk()
        ->assertSet('tab', 'diario')
        ->assertSee('DAILY-1')
        ->assertDontSee('WEEKLY-1');
});

it('la pestaña semanal muestra solo equipos semanales', function (): void {
    Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily', 'code' => 'DAILY-2']);
    Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'weekly', 'code' => 'WEEKLY-2']);

    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'semanal')
        ->assertSet('tab', 'semanal')
        ->assertSee('WEEKLY-2')
        ->assertDontSee('DAILY-2');
});

// ── Captura por celda ────────────────────────────────────────────────────────

it('guardar una celda registra la lectura y actualiza el dial del equipo', function (): void {
    $eq = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'reading_frequency' => 'daily',
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);
    $today = today()->format('Y-m-d');

    Livewire::test(ListMeterReadings::class)
        ->set("draft.{$eq->id}.{$today}", 1500)
        ->call('saveCell', $eq->id, $today)
        ->assertHasNoErrors();

    $reading = EquipmentMeterReading::where('equipment_id', $eq->id)->first();

    expect($reading)->not->toBeNull()
        ->and((float) $reading->reading_value)->toBe(1500.0)
        ->and((float) $eq->refresh()->current_meter_reading)->toBe(1500.0);
});

it('la segunda lectura de la celda calcula las horas como la diferencia', function (): void {
    $eq = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'reading_frequency' => 'daily',
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);
    $yesterday = today()->subDay()->format('Y-m-d');
    $today = today()->format('Y-m-d');

    Livewire::test(ListMeterReadings::class)
        ->set("draft.{$eq->id}.{$yesterday}", 1000)
        ->call('saveCell', $eq->id, $yesterday)
        ->set("draft.{$eq->id}.{$today}", 1020)
        ->call('saveCell', $eq->id, $today);

    $second = EquipmentMeterReading::where('equipment_id', $eq->id)->orderByDesc('recorded_at')->first();

    expect((float) $second->delta)->toBe(20.0);
});

it('una celda vacía no registra nada', function (): void {
    $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily']);
    $today = today()->format('Y-m-d');

    Livewire::test(ListMeterReadings::class)
        ->call('saveCell', $eq->id, $today);

    expect(EquipmentMeterReading::where('equipment_id', $eq->id)->count())->toBe(0);
});

// ── Configurar equipos de las rondas ─────────────────────────────────────────

it('asigna en bloque los equipos a diario/semanal y saca de la ronda a los quitados', function (): void {
    $a = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => null]);
    $b = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => null]);
    // Ya estaba en diario; al no incluirlo, debe quedar sin ronda.
    $c = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily']);

    Livewire::test(ListMeterReadings::class)
        ->callAction('configureEquipment', data: [
            'daily' => [$a->id],
            'weekly' => [$b->id],
        ])
        ->assertHasNoActionErrors();

    expect($a->refresh()->reading_frequency?->value)->toBe('daily')
        ->and($b->refresh()->reading_frequency?->value)->toBe('weekly')
        ->and($c->refresh()->reading_frequency)->toBeNull();
});

it('el modal de configurar equipos se renderiza en la pestaña de matriz', function (): void {
    // La página es HasTable; en las pestañas de matriz no se renderiza la tabla, así
    // que sin el contenedor de modales propio el modal se monta pero no aparece.
    Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily']);

    Livewire::test(ListMeterReadings::class)
        ->assertSet('tab', 'diario')
        ->mountAction('configureEquipment')
        ->assertActionMounted('configureEquipment')
        // El contenedor de modales debe existir en la pestaña de matriz; sin él el
        // modal se monta pero no tiene dónde inyectarse (era el bug).
        ->assertSee('filamentActionModals');
});

it('rechaza un equipo puesto en diario y semanal a la vez', function (): void {
    $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => null]);

    Livewire::test(ListMeterReadings::class)
        ->callAction('configureEquipment', data: [
            'daily' => [$eq->id],
            'weekly' => [$eq->id],
        ]);

    // Se detuvo por el conflicto: el equipo sigue sin ronda.
    expect($eq->refresh()->reading_frequency)->toBeNull();
});
