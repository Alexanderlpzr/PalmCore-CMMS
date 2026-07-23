<?php

use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
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

it('el centro de horómetros arranca en Control y la pestaña diaria muestra solo equipos diarios', function (): void {
    Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily', 'code' => 'DAILY-1']);
    Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'weekly', 'code' => 'WEEKLY-1']);

    Livewire::test(ListMeterReadings::class)
        ->assertOk()
        ->assertSet('tab', 'control')
        ->call('selectTab', 'diario')
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

// ── Corregir y borrar lecturas ya guardadas ──────────────────────────────────

it('corregir una celda actualiza la lectura y recalcula el acumulado', function (): void {
    $eq = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'reading_frequency' => 'daily',
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);
    $yesterday = today()->subDay()->format('Y-m-d');
    $today = today()->format('Y-m-d');

    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'diario')
        ->set("draft.{$eq->id}.{$yesterday}", 1_000)
        ->call('saveCell', $eq->id, $yesterday)
        ->set("draft.{$eq->id}.{$today}", 1_020)
        ->call('saveCell', $eq->id, $today);

    $second = EquipmentMeterReading::where('equipment_id', $eq->id)->orderByDesc('recorded_at')->first();

    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'diario')
        ->set("editDraft.{$second->id}", 1_010)
        ->call('saveEditedReading', $second->id)
        ->assertHasNoErrors();

    expect((float) $second->fresh()->reading_value)->toBe(1_010.0)
        ->and((float) $second->fresh()->delta)->toBe(10.0)
        ->and((float) $eq->refresh()->accumulated_meter_reading)->toBe(10.0)
        ->and((float) $eq->refresh()->current_meter_reading)->toBe(1_010.0);
});

it('vaciar una celda elimina la lectura y recalcula la cadena', function (): void {
    $eq = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'reading_frequency' => 'daily',
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);
    $yesterday = today()->subDay()->format('Y-m-d');
    $today = today()->format('Y-m-d');

    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'diario')
        ->set("draft.{$eq->id}.{$yesterday}", 1_000)
        ->call('saveCell', $eq->id, $yesterday)
        ->set("draft.{$eq->id}.{$today}", 1_020)
        ->call('saveCell', $eq->id, $today);

    $second = EquipmentMeterReading::where('equipment_id', $eq->id)->orderByDesc('recorded_at')->first();

    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'diario')
        ->set("editDraft.{$second->id}", '')
        ->call('saveEditedReading', $second->id);

    expect(EquipmentMeterReading::where('equipment_id', $eq->id)->count())->toBe(1)
        ->and((float) $eq->refresh()->accumulated_meter_reading)->toBe(0.0)
        ->and((float) $eq->refresh()->current_meter_reading)->toBe(1_000.0);
});

it('corregir una lectura del medio arrastra el acumulado de las posteriores', function (): void {
    $eq = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'reading_frequency' => 'daily',
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);
    $service = app(EquipmentMeterReadingService::class);

    // 1000 → 1050 → 1100 : deltas 0, 50, 50 ; acumulado 0, 50, 100.
    $service->record($eq->fresh(), 1_000, $this->user, recordedAt: today()->subDays(2));
    $mid = $service->record($eq->fresh(), 1_050, $this->user, recordedAt: today()->subDay());
    $service->record($eq->fresh(), 1_100, $this->user, recordedAt: today());

    // Corrijo la del medio a 1_060 : deltas 0, 60, 40 ; acumulado 0, 60, 100.
    $service->updateReading($mid->fresh(), 1_060);

    $last = EquipmentMeterReading::where('equipment_id', $eq->id)->orderByDesc('recorded_at')->first();

    expect((float) $mid->fresh()->delta)->toBe(60.0)
        ->and((float) $last->delta)->toBe(40.0)
        ->and((float) $last->accumulated_value)->toBe(100.0)
        ->and((float) $eq->refresh()->accumulated_meter_reading)->toBe(100.0);
});

// ── La ronda del período (captura cómoda) ────────────────────────────────────

it('la vista de captura alterna entre lista y cuadrícula', function (): void {
    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'diario')
        ->assertSet('roundView', 'lista')
        ->call('setRoundView', 'cuadricula')
        ->assertSet('roundView', 'cuadricula')
        ->call('setRoundView', 'lista')
        ->assertSet('roundView', 'lista');
});

it('la ronda muestra la referencia anterior y cuenta los pendientes del período', function (): void {
    $eqA = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily', 'code' => 'RA',
        'accumulated_meter_reading' => 0, 'current_meter_reading' => null,
    ]);
    Equipment::factory()->create([
        'tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily', 'code' => 'RB',
    ]);

    // A tiene lectura de ayer (referencia), ninguna de hoy → los dos quedan pendientes.
    app(EquipmentMeterReadingService::class)->record($eqA, 1_000, $this->user, recordedAt: today()->subDay()->setTime(12, 0));

    $data = Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'diario')
        ->instance()
        ->getRoundData();

    $rowA = collect($data['rows'])->firstWhere('code', 'RA');

    expect($data['total'])->toBe(2)
        ->and($data['pending'])->toBe(2)
        ->and($rowA['reference'])->toBe(1_000.0)
        ->and($rowA['filled'])->toBeFalse();
});

it('la ronda marca leído el equipo con lectura del período y calcula sus horas', function (): void {
    $eq = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily', 'code' => 'RC',
        'accumulated_meter_reading' => 0, 'current_meter_reading' => null,
    ]);
    $service = app(EquipmentMeterReadingService::class);
    $service->record($eq, 1_000, $this->user, recordedAt: today()->subDay()->setTime(12, 0));
    $service->record($eq->fresh(), 1_020, $this->user, recordedAt: today()->setTime(12, 0));

    $data = Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'diario')
        ->instance()
        ->getRoundData();

    $row = collect($data['rows'])->firstWhere('code', 'RC');

    expect($data['pending'])->toBe(0)
        ->and($row['filled'])->toBeTrue()
        ->and($row['hours'])->toBe(20.0)
        ->and($row['reference'])->toBe(1_000.0);
});

// ── Horas trabajadas (consolidado del horómetro) ─────────────────────────────

it('el resumen de horas trabajadas suma los deltas del horómetro del período', function (): void {
    $eq = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id, 'code' => 'WH-1', 'reading_frequency' => 'daily',
        'accumulated_meter_reading' => 0, 'current_meter_reading' => null,
    ]);
    $service = app(EquipmentMeterReadingService::class);

    // Del mes en curso: 1000 (delta 0), 1050 (delta 50), 1080 (delta 30) → 80 h.
    $anchor = now()->startOfMonth();
    $service->record($eq, 1_000, $this->user, recordedAt: $anchor->copy()->addDays(1)->setTime(12, 0));
    $service->record($eq->fresh(), 1_050, $this->user, recordedAt: $anchor->copy()->addDays(2)->setTime(12, 0));
    $service->record($eq->fresh(), 1_080, $this->user, recordedAt: $anchor->copy()->addDays(3)->setTime(12, 0));

    $report = Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'horas')
        ->set('whMode', 'mensual')
        ->set('whYear', (int) now()->year)
        ->set('whMonth', (int) now()->month)
        ->instance()
        ->workedHoursReport();

    $row = collect($report['rows'])->firstWhere('code', 'WH-1');

    expect($row['total_hours'])->toBe(80.0)
        ->and($report['total'])->toBe(80.0);
});

it('la pestaña de horas trabajadas se renderiza', function (): void {
    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'horas')
        ->assertSet('tab', 'horas')
        ->assertSee('Calculado del horómetro');
});

// ── Configurar equipos de las rondas ─────────────────────────────────────────

it('asigna en bloque los equipos a diario/semanal y saca de la ronda a los quitados', function (): void {
    $a = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => null]);
    $b = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => null]);
    // Ya estaba en diario; al no incluirlo, debe quedar sin ronda.
    $c = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily']);

    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'diario')
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
        ->call('selectTab', 'diario')
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
        ->call('selectTab', 'diario')
        ->callAction('configureEquipment', data: [
            'daily' => [$eq->id],
            'weekly' => [$eq->id],
        ]);

    // Se detuvo por el conflicto: el equipo sigue sin ronda.
    expect($eq->refresh()->reading_frequency)->toBeNull();
});
