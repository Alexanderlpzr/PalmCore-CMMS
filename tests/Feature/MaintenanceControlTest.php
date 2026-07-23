<?php

use App\Filament\Resources\MeterReadings\Pages\ListMeterReadings;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
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

/**
 * Un plan por horómetro con su vencimiento, tal como lo espera el tablero de Control.
 */
function meterControlPlan(
    Tenant $tenant,
    Equipment $equipment,
    int $interval,
    float $nextDue,
    ?float $lastCompleted = null,
    ?string $componentId = null,
    ?int $lead = null,
): MaintenancePlan {
    $plan = MaintenancePlan::factory()->meterBased($interval)->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'equipment_component_id' => $componentId,
        'meter_lead_hours' => $lead,
        'plan_number' => 'PM-'.Str::upper(Str::random(8)),
        'is_active' => true,
    ]);

    MaintenanceSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_meter' => $nextDue,
        'last_completed_meter' => $lastCompleted,
        'next_due_at' => null,
    ]);

    return $plan->load('schedule');
}

// ── El tablero ────────────────────────────────────────────────────────────────

it('agrupa las tareas por equipo y calcula el semáforo de vencimiento', function (): void {
    $eq = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'PRE-01',
        'accumulated_meter_reading' => 1_914,
    ]);
    // Faltan 86 h (≤ 150 por defecto) → ámbar.
    meterControlPlan($this->tenant, $eq, 2_000, 2_000);

    $groups = Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'control')
        ->instance()
        ->controlGroups();

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['equipment']['code'])->toBe('PRE-01')
        ->and($groups[0]['rows'])->toHaveCount(1)
        ->and($groups[0]['rows'][0]['remaining'])->toBe(86.0)
        ->and($groups[0]['rows'][0]['color'])->toBe('warning');
});

it('muestra en rojo y con horas negativas una tarea vencida', function (): void {
    $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'accumulated_meter_reading' => 2_624]);
    meterControlPlan($this->tenant, $eq, 2_000, 2_000); // faltan -624

    $row = Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'control')
        ->instance()
        ->controlGroups()[0]['rows'][0];

    expect($row['remaining'])->toBe(-624.0)
        ->and($row['color'])->toBe('danger');
});

// ── Buscador y ciclos ──────────────────────────────────────────────────────────

it('el buscador filtra el tablero por equipo', function (): void {
    $bomba = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'BOMBA-01', 'accumulated_meter_reading' => 0]);
    $redler = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'REDLER-02', 'accumulated_meter_reading' => 0]);
    meterControlPlan($this->tenant, $bomba, 2_000, 2_000);
    meterControlPlan($this->tenant, $redler, 2_000, 2_000);

    $groups = Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'control')
        ->set('controlSearch', 'redler')
        ->instance()
        ->controlGroups();

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['equipment']['code'])->toBe('REDLER-02');
});

it('la columna de ciclos cuenta los mantenimientos hechos', function (): void {
    $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'accumulated_meter_reading' => 1_914]);
    $plan = meterControlPlan($this->tenant, $eq, 2_000, 2_000, lastCompleted: 0);

    $component = Livewire::test(ListMeterReadings::class)->call('selectTab', 'control');

    $before = collect($component->instance()->controlGroups()[0]['rows'])->firstWhere('plan_id', $plan->id);
    expect($before['cycles'])->toBe(0);

    $component->call('registrarMantenimiento', $plan->id);

    $after = collect($component->instance()->controlGroups()[0]['rows'])->firstWhere('plan_id', $plan->id);
    expect($after['cycles'])->toBe(1);
});

// ── Edición en la celda ────────────────────────────────────────────────────────

it('editar la frecuencia recalcula el próximo mantenimiento', function (): void {
    $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'accumulated_meter_reading' => 1_000]);
    $plan = meterControlPlan($this->tenant, $eq, 2_000, 2_000, lastCompleted: 0);

    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'control')
        ->set("controlDraft.{$plan->id}.meter_interval", 3_000)
        ->call('saveControlCell', $plan->id, 'meter_interval');

    expect($plan->fresh()->meter_interval)->toBe(3_000)
        ->and($plan->schedule->fresh()->next_due_meter)->toBe(3_000.0); // 0 + 3000
});

it('editar el horómetro del último mtto recalcula el próximo', function (): void {
    $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'accumulated_meter_reading' => 1_000]);
    $plan = meterControlPlan($this->tenant, $eq, 2_000, 2_000, lastCompleted: 0);

    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'control')
        ->set("controlDraft.{$plan->id}.last_completed_meter", 500)
        ->call('saveControlCell', $plan->id, 'last_completed_meter');

    expect($plan->schedule->fresh()->last_completed_meter)->toBe(500.0)
        ->and($plan->schedule->fresh()->next_due_meter)->toBe(2_500.0); // 500 + 2000
});

it('una frecuencia de cero se rechaza', function (): void {
    $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'accumulated_meter_reading' => 1_000]);
    $plan = meterControlPlan($this->tenant, $eq, 2_000, 2_000);

    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'control')
        ->set("controlDraft.{$plan->id}.meter_interval", 0)
        ->call('saveControlCell', $plan->id, 'meter_interval');

    expect($plan->fresh()->meter_interval)->toBe(2_000);
});

// ── Registrar mantenimiento ────────────────────────────────────────────────────

it('registrar mantenimiento fija el último al horómetro actual y adelanta el próximo', function (): void {
    $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'accumulated_meter_reading' => 1_914]);
    $plan = meterControlPlan($this->tenant, $eq, 2_000, 2_000, lastCompleted: 0);

    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'control')
        ->call('registrarMantenimiento', $plan->id);

    $schedule = $plan->schedule->fresh();

    expect($schedule->last_completed_meter)->toBe(1_914.0)
        ->and($schedule->next_due_meter)->toBe(3_914.0) // 1914 + 2000
        ->and($schedule->times_executed)->toBe(1);
});

// ── Crear OT ───────────────────────────────────────────────────────────────────

it('crear OT genera la orden preventiva de la tarea', function (): void {
    $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'accumulated_meter_reading' => 1_950]);
    $plan = meterControlPlan($this->tenant, $eq, 2_000, 2_000); // faltan 50 → ámbar

    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'control')
        ->call('crearOt', $plan->id);

    expect(WorkOrder::withoutGlobalScopes()->where('maintenance_plan_id', $plan->id)->count())->toBe(1);
});

it('crear OT no duplica si ya hay una abierta para la tarea', function (): void {
    $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'accumulated_meter_reading' => 1_950]);
    $plan = meterControlPlan($this->tenant, $eq, 2_000, 2_000);

    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $eq->id,
        'maintenance_plan_id' => $plan->id,
        'status' => 'in_progress',
    ]);

    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'control')
        ->call('crearOt', $plan->id);

    expect(WorkOrder::withoutGlobalScopes()->where('maintenance_plan_id', $plan->id)->count())->toBe(1);
});

// ── Agregar tarea ──────────────────────────────────────────────────────────────

it('agregar tarea crea y activa un plan con próximo = último + frecuencia', function (): void {
    $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'accumulated_meter_reading' => 0]);

    Livewire::test(ListMeterReadings::class)
        ->call('selectTab', 'control')
        ->callAction('addControlTask', data: [
            'equipment_id' => $eq->id,
            'name' => 'cambio aceite reductor',
            'meter_interval' => 9_000,
            'last_completed_meter' => 1_550,
            'meter_lead_hours' => 150,
        ])
        ->assertHasNoActionErrors();

    $plan = MaintenancePlan::where('equipment_id', $eq->id)->where('name', 'cambio aceite reductor')->first();

    expect($plan)->not->toBeNull()
        ->and($plan->is_active)->toBeTrue()
        ->and($plan->meter_interval)->toBe(9_000)
        ->and($plan->schedule->last_completed_meter)->toBe(1_550.0)
        ->and($plan->schedule->next_due_meter)->toBe(10_550.0); // 1550 + 9000
});

// ── Permisos ───────────────────────────────────────────────────────────────────

it('un usuario sin permiso de planes no ve el tablero ni puede editarlo', function (): void {
    $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'accumulated_meter_reading' => 1_000]);
    $plan = meterControlPlan($this->tenant, $eq, 2_000, 2_000);

    $viewer = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $viewer->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->actingAs($viewer);

    $page = new ListMeterReadings;

    expect($page->controlTabVisible())->toBeFalse()
        ->and($page->canWriteControl())->toBeFalse();

    // Y una escritura directa se corta antes de tocar nada.
    $page->controlDraft[$plan->id]['meter_interval'] = 9_999;
    $page->saveControlCell($plan->id, 'meter_interval');

    expect($plan->fresh()->meter_interval)->toBe(2_000);
});
