<?php

use App\Filament\Pages\DailyMeterRegister;
use App\Filament\Pages\WeeklyMeterRegister;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

// ── La matriz muestra solo su frecuencia ─────────────────────────────────────

it('el registro diario muestra solo los equipos diarios', function (): void {
    Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily', 'code' => 'DAILY-1']);
    Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'weekly', 'code' => 'WEEKLY-1']);

    Livewire::test(DailyMeterRegister::class)
        ->assertOk()
        ->assertSee('DAILY-1')
        ->assertDontSee('WEEKLY-1');
});

it('el registro semanal muestra solo los equipos semanales', function (): void {
    Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily', 'code' => 'DAILY-2']);
    Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'weekly', 'code' => 'WEEKLY-2']);

    Livewire::test(WeeklyMeterRegister::class)
        ->assertOk()
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

    Livewire::test(DailyMeterRegister::class)
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

    Livewire::test(DailyMeterRegister::class)
        ->set("draft.{$eq->id}.{$yesterday}", 1000)
        ->call('saveCell', $eq->id, $yesterday)
        ->set("draft.{$eq->id}.{$today}", 1020)
        ->call('saveCell', $eq->id, $today);

    // 1020 − 1000 = 20 horas trabajadas en la segunda lectura.
    $second = EquipmentMeterReading::where('equipment_id', $eq->id)->orderByDesc('recorded_at')->first();

    expect((float) $second->delta)->toBe(20.0);
});

it('una celda vacía no registra nada', function (): void {
    $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily']);
    $today = today()->format('Y-m-d');

    Livewire::test(DailyMeterRegister::class)
        ->call('saveCell', $eq->id, $today);

    expect(EquipmentMeterReading::where('equipment_id', $eq->id)->count())->toBe(0);
});
