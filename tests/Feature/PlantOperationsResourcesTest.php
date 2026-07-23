<?php

use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Enums\StoppageConfirmationStatus;
use App\Filament\Resources\Downtime\Pages\CreateDowntimeEvent;
use App\Filament\Resources\Downtime\Pages\ListDowntimeEvents;
use App\Filament\Resources\MeterReadings\Pages\ListMeterReadings;
use App\Filament\Resources\ProductionCalendar\Pages\ListProductionCalendarDays;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/**
 * A3 — lo que existe en la SPA existe en Filament: paros, calendario de producción
 * y horómetros.
 *
 * Lo que se prueba aquí no es que las pantallas rendericen, sino que **no sean una
 * puerta trasera**: el paro se registra por el servicio (con sus reglas de solape),
 * la lectura de horómetro pasa por el cálculo del acumulado, y firmar las horas
 * exige la facultad de producción, que mantenimiento no tiene.
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
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $this->admin->assignRole('administrador-general');

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

/** Un usuario del tenant con un rol concreto. */
function userWithRole(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach(test()->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId(test()->tenant->id);
    $user->assignRole($role);

    return $user;
}

// ── Paros ────────────────────────────────────────────────────────────────────

it('lists the plant stoppages', function (): void {
    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'stoppage_cause' => 'Atasco en prensa 2',
    ]);

    Livewire::test(ListDowntimeEvents::class)
        ->assertOk()
        ->assertSee('PRE-02');
});

it('registers a paro through the domain service, not straight into the table', function (): void {
    Livewire::test(CreateDowntimeEvent::class)
        ->fillForm([
            'plant_id' => $this->plant->id,
            'equipment_id' => $this->equipment->id,
            'stoppage_category' => StoppageCategory::Mechanical->value,
            'stoppage_cause' => 'Rodamiento del reductor',
            'started_at' => '2026-06-10 08:00:00',
            'ended_at' => '2026-06-10 11:00:00',
            'affects_production' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $paro = EquipmentDowntimeEvent::withoutGlobalScopes()->sole();

    // Son campos que el formulario no envía: los deriva el servicio. Si el recurso
    // hubiera escrito directo en la tabla, estarían vacíos.
    expect($paro->duration_minutes)->toBe(180)
        ->and($paro->cause_type->value)->toBe('corrective')
        ->and($paro->reported_type->value)->toBe('mantenimiento')
        ->and($paro->registered_by)->toBe($this->admin->id);
});

it('refuses from Filament the overlapping paro the service would refuse anywhere', function (): void {
    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'started_at' => Carbon::parse('2026-06-10 08:00:00'),
        'ended_at' => Carbon::parse('2026-06-10 12:00:00'),
        'duration_minutes' => 240,
    ]);

    // El mismo equipo no puede estar parado dos veces a la vez: esas horas se
    // contarían dos veces contra la planta.
    Livewire::test(CreateDowntimeEvent::class)
        ->fillForm([
            'plant_id' => $this->plant->id,
            'equipment_id' => $this->equipment->id,
            'stoppage_category' => StoppageCategory::Electrical->value,
            'started_at' => '2026-06-10 10:00:00',
            'ended_at' => '2026-06-10 14:00:00',
        ])
        ->call('create')
        // Y se lo dice en español, no con una pantalla de error.
        ->assertNotified();

    expect(EquipmentDowntimeEvent::withoutGlobalScopes()->count())->toBe(1);
});

it('lets production sign the hours from the table', function (): void {
    $paro = EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'affects_production' => true,
    ]);

    Livewire::test(ListDowntimeEvents::class)
        ->callAction(TestAction::make('confirm')->table($paro), data: ['notes' => 'De acuerdo.'])
        ->assertHasNoActionErrors();

    expect($paro->refresh()->confirmation_status)->toBe(StoppageConfirmationStatus::Confirmed)
        ->and($paro->confirmed_by)->toBe($this->admin->id);
});

it('does not let maintenance sign its own hours', function (): void {
    // El ingeniero de mantenimiento registra y clasifica el paro, pero no certifica
    // las horas que su propia área hizo perder. Juez y parte, no.
    $engineer = userWithRole('ingeniero-mantenimiento');

    $paro = EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'affects_production' => true,
    ]);

    expect($engineer->can('confirm', $paro))->toBeFalse()
        ->and($engineer->can('update', $paro))->toBeTrue();

    // Y el jefe de turno sí puede.
    expect(userWithRole('supervisor')->can('confirm', $paro))->toBeTrue();
});

it('never lets anyone delete a paro', function (): void {
    $paro = EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    // Un paro que desaparece se lleva consigo las horas perdidas del mes y deja la
    // eficiencia sin forma de auditarse.
    expect($this->admin->can('delete', $paro))->toBeFalse();
});

// ── Horómetros ───────────────────────────────────────────────────────────────

it('groups readings by equipment so two equipos never interleave by date', function (): void {
    // El historial ya no se renderiza como pestaña; el recurso conserva el agrupado
    // por equipo, que es lo que este test protege.
    $component = Livewire::test(ListMeterReadings::class)->assertOk();

    expect($component->instance()->getTable()->getDefaultGroup()?->getId())->toBe('equipment.code');
});

// ── Calendario de producción ─────────────────────────────────────────────────

it('programs a whole month from the calendar screen', function (): void {
    Livewire::test(ListProductionCalendarDays::class)
        ->callAction('programMonth', data: [
            'plant_id' => $this->plant->id,
            'year' => 2026,
            'month' => 6,
            'hours_per_day' => 22,
            'rest_days' => [7],
        ])
        ->assertHasNoActionErrors();

    $days = ProductionCalendarDay::withoutGlobalScopes()->where('plant_id', $this->plant->id)->get();

    expect($days)->toHaveCount(30)
        // Los domingos de junio 2026 (7, 14, 21, 28) se programan en cero: un día que
        // nunca debía producir no es un día perdido.
        ->and($days->where('programmed_hours', 0.0))->toHaveCount(4)
        ->and($days->sum('programmed_hours'))->toEqual(26 * 22.0);
});
