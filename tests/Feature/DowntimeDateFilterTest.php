<?php

use App\Filament\Resources\Downtime\Pages\ListDowntimeEvents;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * El filtro por fechas de Paros. La pregunta habitual sobre los paros es «qué pasó la
 * semana pasada», y hasta ahora había que ordenar por fecha y desplazarse.
 *
 * Lo que más se prueba aquí es el borde: que «hasta el 10» incluya el día 10 entero. Es
 * el error clásico de comparar contra un timestamp en vez de contra la fecha, y se nota
 * poco porque solo falta el último día.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $this->admin->assignRole('administrador-general');

    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

function paroEl(string $cuando): EquipmentDowntimeEvent
{
    return EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => test()->tenant->id,
        'plant_id' => test()->equipment->plant_id,
        'equipment_id' => test()->equipment->id,
        'started_at' => Carbon::parse($cuando),
    ]);
}

it('sin filtro muestra todos los paros', function (): void {
    $agosto = paroEl('2026-08-05 08:00');
    $septiembre = paroEl('2026-09-05 08:00');

    Livewire::test(ListDowntimeEvents::class)
        ->assertCanSeeTableRecords([$agosto, $septiembre]);
});

it('filtra desde una fecha', function (): void {
    $viejo = paroEl('2026-08-05 08:00');
    $nuevo = paroEl('2026-09-05 08:00');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('rango_de_fechas', ['desde' => '2026-09-01'])
        ->assertCanSeeTableRecords([$nuevo])
        ->assertCanNotSeeTableRecords([$viejo]);
});

it('filtra hasta una fecha', function (): void {
    $viejo = paroEl('2026-08-05 08:00');
    $nuevo = paroEl('2026-09-05 08:00');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('rango_de_fechas', ['hasta' => '2026-08-31'])
        ->assertCanSeeTableRecords([$viejo])
        ->assertCanNotSeeTableRecords([$nuevo]);
});

it('incluye el día final entero', function (): void {
    // El paro de las 23:30 del último día del rango tiene que entrar. Comparar contra el
    // timestamp en vez de contra la fecha lo dejaría fuera, y nadie lo notaría hasta que
    // faltara justo el paro de la noche del cierre de mes.
    $alFilo = paroEl('2026-08-10 23:30');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('rango_de_fechas', ['desde' => '2026-08-01', 'hasta' => '2026-08-10'])
        ->assertCanSeeTableRecords([$alFilo]);
});

it('incluye el día inicial desde la medianoche', function (): void {
    $alFilo = paroEl('2026-08-01 00:15');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('rango_de_fechas', ['desde' => '2026-08-01'])
        ->assertCanSeeTableRecords([$alFilo]);
});

it('filtra por los dos extremos a la vez', function (): void {
    $antes = paroEl('2026-07-31 10:00');
    $dentro = paroEl('2026-08-15 10:00');
    $despues = paroEl('2026-09-01 10:00');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('rango_de_fechas', ['desde' => '2026-08-01', 'hasta' => '2026-08-31'])
        ->assertCanSeeTableRecords([$dentro])
        ->assertCanNotSeeTableRecords([$antes, $despues]);
});

it('el filtro de fecha convive con los demás filtros', function (): void {
    // Filtrar por fecha no debe pisar el filtro de estado ni al revés.
    $cerradoEnAgosto = paroEl('2026-08-10 08:00');
    $enCursoEnAgosto = EquipmentDowntimeEvent::factory()->ongoing()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->equipment->plant_id,
        'equipment_id' => $this->equipment->id,
        'started_at' => Carbon::parse('2026-08-12 08:00'),
    ]);

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('rango_de_fechas', ['desde' => '2026-08-01', 'hasta' => '2026-08-31'])
        ->filterTable('ended_at', false)
        ->assertCanSeeTableRecords([$enCursoEnAgosto])
        ->assertCanNotSeeTableRecords([$cerradoEnAgosto]);
});
