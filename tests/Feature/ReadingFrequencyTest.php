<?php

use App\Domain\Assets\Enums\MeterReadingFrequency;
use App\Filament\Resources\MeterReadings\Pages\ListMeterReadings;
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

// ── El campo ─────────────────────────────────────────────────────────────────

it('clasifica un equipo como diario o semanal y lo castea al enum', function (): void {
    $daily = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily']);
    $weekly = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'weekly']);
    $none = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => null]);

    expect($daily->reading_frequency)->toBe(MeterReadingFrequency::Daily)
        ->and($weekly->reading_frequency)->toBe(MeterReadingFrequency::Weekly)
        ->and($none->reading_frequency)->toBeNull()
        ->and(MeterReadingFrequency::Daily->label())->toBe('Diario')
        ->and(MeterReadingFrequency::Weekly->label())->toBe('Semanal');
});

// ── El filtro de Horómetros ──────────────────────────────────────────────────

it('el filtro de ronda muestra solo las lecturas de los equipos de esa frecuencia', function (): void {
    $dailyEquip = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'daily']);
    $weeklyEquip = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'reading_frequency' => 'weekly']);

    $dailyReading = EquipmentMeterReading::factory()->create([
        'tenant_id' => $this->tenant->id, 'equipment_id' => $dailyEquip->id,
    ]);
    $weeklyReading = EquipmentMeterReading::factory()->create([
        'tenant_id' => $this->tenant->id, 'equipment_id' => $weeklyEquip->id,
    ]);

    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'historial')
        ->filterTable('reading_frequency', 'daily')
        ->assertCanSeeTableRecords([$dailyReading])
        ->assertCanNotSeeTableRecords([$weeklyReading]);
});
