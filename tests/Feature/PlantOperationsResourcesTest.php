<?php

use App\Domain\Analytics\Services\ProductionCalendarService;
use App\Domain\Assets\Enums\PlantSection;
use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageConfirmationStatus;
use App\Domain\Assets\Enums\StoppageReason;
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
use Carbon\CarbonInterface;
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
 * exige el permiso downtime-events.confirm.
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

/** Un usuario del tenant, con rol o sin ninguno. */
function userWithRole(?string $role = null): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->tenants()->attach(test()->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId(test()->tenant->id);

    if ($role !== null) {
        $user->assignRole($role);
    }

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
        // El Tipo I primero: al cambiarlo se limpia el Tipo II dependiente.
        ->fillForm(['reported_type' => ReportedStoppageType::Maintenance->value])
        ->fillForm([
            'plant_id' => $this->plant->id,
            'section' => PlantSection::Extraccion->value,
            'equipment_id' => $this->equipment->id,
            'stoppage_reason' => StoppageReason::FallaMecanica->value,
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
        ->fillForm(['reported_type' => ReportedStoppageType::Maintenance->value])
        ->fillForm([
            'plant_id' => $this->plant->id,
            'section' => PlantSection::Extraccion->value,
            'equipment_id' => $this->equipment->id,
            'stoppage_reason' => StoppageReason::FallaElectrica->value,
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

it('requires the confirm permission to sign the hours of a paro', function (): void {
    // Antes esto separaba funciones: mantenimiento registraba el paro y producción
    // firmaba las horas, para que nadie fuera juez y parte. Al colapsar la matriz a
    // un solo rol de tenant esa separación desaparece — el administrador registra y
    // firma. Lo que sigue en pie es que sin el permiso no se firma.
    $sinRol = userWithRole();

    $paro = EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->equipment->id,
        'affects_production' => true,
    ]);

    expect($sinRol->can('confirm', $paro))->toBeFalse()
        ->and($sinRol->can('update', $paro))->toBeFalse();

    expect(userWithRole('administrador-general')->can('confirm', $paro))->toBeTrue();
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

it('agrupa el listado por mes, y el total de un mes solo suma sus días', function (): void {
    $servicio = app(ProductionCalendarService::class);
    $servicio->upsertDay($this->plant, Carbon::parse('2026-08-03'), 16, 196.35);
    $servicio->upsertDay($this->plant, Carbon::parse('2026-08-04'), 16, 223.75);
    // El vecino: si el alcance del grupo discrepara de su clave, este se colaría.
    $servicio->upsertDay($this->plant, Carbon::parse('2026-07-31'), 20, 999.0);

    $grupo = Livewire::test(ListProductionCalendarDays::class)
        ->assertOk()
        ->instance()
        ->getTable()
        ->getDefaultGroup();

    expect($grupo?->getId())->toBe('calendar_date');

    $dia = ProductionCalendarDay::withoutGlobalScopes()
        ->where('calendar_date', '2026-08-03')
        ->first();

    // La clave y el título salen del mismo día: agosto no puede ser dos grupos.
    expect($grupo->getKey($dia))->toBe('2026-08')
        ->and((string) $grupo->getTitle($dia))->toContain('Agosto');

    $suma = $grupo->scopeQueryByKey(
        ProductionCalendarDay::withoutGlobalScopes()->where('plant_id', $this->plant->id),
        '2026-08',
    )->sum('processed_tons');

    // 196,35 + 223,75, sin el 31 de julio.
    expect((float) $suma)->toBe(420.10);
});

it('ordena los meses con el más reciente arriba, como las filas', function (): void {
    // Con el orden por defecto los meses subirían de enero a diciembre mientras los días
    // de dentro bajan, y la tabla se leería en dos direcciones a la vez.
    $servicio = app(ProductionCalendarService::class);
    $servicio->upsertDay($this->plant, Carbon::parse('2026-07-15'), 16, 100.0);
    $servicio->upsertDay($this->plant, Carbon::parse('2026-08-15'), 16, 200.0);

    $fechas = Livewire::test(ListProductionCalendarDays::class)
        ->assertOk()
        ->instance()
        ->getTableRecords()
        ->pluck('calendar_date')
        ->map(fn (CarbonInterface $f): string => $f->toDateString())
        ->all();

    expect($fechas[0])->toBe('2026-08-15');
});
