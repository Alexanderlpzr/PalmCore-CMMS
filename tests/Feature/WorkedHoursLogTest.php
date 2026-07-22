<?php

use App\Filament\Pages\WorkedHoursLog;
use App\Models\Equipment;
use App\Models\EquipmentWorkedHours;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/**
 * Horas trabajadas: diario/semanal capturan (equipo + fecha + horas), mensual/
 * anual solo suman lo capturado — nunca se escribe un registro "mensual" o
 * "anual" en la base.
 */
beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'code' => 'PRE-02',
    ]);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $this->admin->assignRole('administrador-general');

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('registers a daily entry with date and hours worked', function (): void {
    Livewire::test(WorkedHoursLog::class)
        ->assertSet('viewMode', 'diario')
        ->callAction('registerWorkedHours', data: [
            'equipment_id' => $this->equipment->id,
            'log_date' => now()->toDateString(),
            'hours' => 8.5,
        ])
        ->assertHasNoActionErrors();

    $entry = EquipmentWorkedHours::withoutGlobalScopes()->sole();

    expect($entry->period_type->value)->toBe('diario')
        ->and($entry->hours)->toBe(8.5)
        ->and($entry->equipment_id)->toBe($this->equipment->id);
});

it('registers a weekly entry once the selector is switched to semanal', function (): void {
    Livewire::test(WorkedHoursLog::class)
        ->set('viewMode', 'semanal')
        ->callAction('registerWorkedHours', data: [
            'equipment_id' => $this->equipment->id,
            'log_date' => now()->toDateString(),
            'hours' => 44,
        ])
        ->assertHasNoActionErrors();

    $entry = EquipmentWorkedHours::withoutGlobalScopes()->sole();

    expect($entry->period_type->value)->toBe('semanal')
        ->and($entry->hours)->toBe(44.0);
});

it('only lists entries matching the selected period type', function (): void {
    EquipmentWorkedHours::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'period_type' => 'diario',
        'hours' => 7,
    ]);
    EquipmentWorkedHours::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'period_type' => 'semanal',
        'hours' => 40,
    ]);

    Livewire::test(WorkedHoursLog::class)
        ->assertCanSeeTableRecords(EquipmentWorkedHours::where('period_type', 'diario')->get())
        ->assertCanNotSeeTableRecords(EquipmentWorkedHours::where('period_type', 'semanal')->get())
        ->set('viewMode', 'semanal')
        ->assertCanSeeTableRecords(EquipmentWorkedHours::where('period_type', 'semanal')->get())
        ->assertCanNotSeeTableRecords(EquipmentWorkedHours::where('period_type', 'diario')->get());
});

it('sums daily and weekly hours per equipment for the vista mensual', function (): void {
    $otherEquipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'code' => 'PRE-09',
    ]);

    EquipmentWorkedHours::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'period_type' => 'diario',
        'log_date' => now()->startOfMonth()->addDays(2),
        'hours' => 8,
    ]);
    EquipmentWorkedHours::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'period_type' => 'semanal',
        'log_date' => now()->startOfMonth()->addDays(9),
        'hours' => 40,
    ]);
    // Fuera del mes actual — no debe sumarse.
    EquipmentWorkedHours::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'period_type' => 'diario',
        'log_date' => now()->subMonths(2),
        'hours' => 99,
    ]);
    // Otro equipo, mismo mes — debe aparecer aparte.
    EquipmentWorkedHours::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $otherEquipment->id,
        'period_type' => 'diario',
        'log_date' => now()->startOfMonth()->addDay(),
        'hours' => 5,
    ]);

    Livewire::test(WorkedHoursLog::class)
        ->set('viewMode', 'mensual')
        ->assertSee('48.00')
        ->assertSee('5.00')
        ->assertDontSee('99.00');
});

it('sums hours across the whole year for the vista anual', function (): void {
    EquipmentWorkedHours::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'period_type' => 'diario',
        'log_date' => now()->startOfYear()->addMonth(),
        'hours' => 20,
    ]);
    EquipmentWorkedHours::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'period_type' => 'semanal',
        'log_date' => now()->endOfYear()->subDays(2),
        'hours' => 30,
    ]);
    EquipmentWorkedHours::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'period_type' => 'diario',
        'log_date' => now()->subYears(2),
        'hours' => 999,
    ]);

    Livewire::test(WorkedHoursLog::class)
        ->set('viewMode', 'anual')
        ->assertSee('50.00')
        ->assertDontSee('999.00');
});

it('never lets a worked-hours entry be edited or deleted', function (): void {
    $entry = EquipmentWorkedHours::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    expect($this->admin->can('update', $entry))->toBeFalse()
        ->and($this->admin->can('delete', $entry))->toBeFalse();
});
