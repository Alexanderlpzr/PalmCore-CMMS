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
 * El filtro por fechas de Paros —desde 2026-11-16 la barra «Periodo · Año · Mes» a la vista,
 * la misma de talento humano ({@see \App\Filament\Filters\PeriodFilter})—. La pregunta habitual sobre los paros es «qué pasó la
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
        ->filterTable('periodo', ['preset' => 'rango', 'desde' => '2026-09-01'])
        ->assertCanSeeTableRecords([$nuevo])
        ->assertCanNotSeeTableRecords([$viejo]);
});

it('filtra hasta una fecha', function (): void {
    $viejo = paroEl('2026-08-05 08:00');
    $nuevo = paroEl('2026-09-05 08:00');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('periodo', ['preset' => 'rango', 'hasta' => '2026-08-31'])
        ->assertCanSeeTableRecords([$viejo])
        ->assertCanNotSeeTableRecords([$nuevo]);
});

it('incluye el día final entero', function (): void {
    // El paro de las 23:30 del último día del rango tiene que entrar. Comparar contra el
    // timestamp en vez de contra la fecha lo dejaría fuera, y nadie lo notaría hasta que
    // faltara justo el paro de la noche del cierre de mes.
    $alFilo = paroEl('2026-08-10 23:30');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('periodo', ['preset' => 'rango', 'desde' => '2026-08-01', 'hasta' => '2026-08-10'])
        ->assertCanSeeTableRecords([$alFilo]);
});

it('incluye el día inicial desde la medianoche', function (): void {
    $alFilo = paroEl('2026-08-01 00:15');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('periodo', ['preset' => 'rango', 'desde' => '2026-08-01'])
        ->assertCanSeeTableRecords([$alFilo]);
});

it('filtra por los dos extremos a la vez', function (): void {
    $antes = paroEl('2026-07-31 10:00');
    $dentro = paroEl('2026-08-15 10:00');
    $despues = paroEl('2026-09-01 10:00');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('periodo', ['preset' => 'mes', 'anio' => 2026, 'mes' => 8])
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
        ->filterTable('periodo', ['preset' => 'mes', 'anio' => 2026, 'mes' => 8])
        ->filterTable('ended_at', false)
        ->assertCanSeeTableRecords([$enCursoEnAgosto])
        ->assertCanNotSeeTableRecords([$cerradoEnAgosto]);
});

// ── Mes, año y últimos días ──────────────────────────────────────────────────

it('un mes deja fuera el mes anterior', function (): void {
    Carbon::setTestNow('2026-08-14');

    $esteMes = paroEl('2026-08-05 08:00');
    $mesPasado = paroEl('2026-07-20 08:00');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('periodo', ['preset' => 'mes', 'anio' => 2026, 'mes' => 8])
        ->assertCanSeeTableRecords([$esteMes])
        ->assertCanNotSeeTableRecords([$mesPasado]);

    Carbon::setTestNow();
});

it('el mes anterior elegido un 31 de mayo es abril entero', function (): void {
    // Un 31 de mayo, «el mes anterior» calculado con `subMonth()` cae en el 31 de abril, que
    // no existe, y Carbon lo desborda al 1 de mayo. La barra elige el mes por su número y
    // no resta, pero el caso queda probado.
    Carbon::setTestNow('2026-05-31');

    $primeroDeAbril = paroEl('2026-04-01 08:00');
    $ultimoDeAbril = paroEl('2026-04-30 22:00');
    $mayo = paroEl('2026-05-02 08:00');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('periodo', ['preset' => 'mes', 'anio' => 2026, 'mes' => 4])
        ->assertCanSeeTableRecords([$primeroDeAbril, $ultimoDeAbril])
        ->assertCanNotSeeTableRecords([$mayo]);

    Carbon::setTestNow();
});

it('«últimos 7 días» incluye hoy y el séptimo día hacia atrás', function (): void {
    Carbon::setTestNow('2026-08-14');

    $hoy = paroEl('2026-08-14 06:00');
    $septimo = paroEl('2026-08-08 23:00');
    $octavo = paroEl('2026-08-07 23:00');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('periodo', ['preset' => 'ultimos_7'])
        ->assertCanSeeTableRecords([$hoy, $septimo])
        ->assertCanNotSeeTableRecords([$octavo]);

    Carbon::setTestNow();
});

it('un año deja fuera el anterior', function (): void {
    Carbon::setTestNow('2026-08-14');

    $esteAnio = paroEl('2026-01-02 08:00');
    $anioPasado = paroEl('2025-12-30 08:00');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('periodo', ['preset' => 'anio', 'anio' => 2026])
        ->assertCanSeeTableRecords([$esteAnio])
        ->assertCanNotSeeTableRecords([$anioPasado]);

    Carbon::setTestNow();
});

// ── Las fechas del rango solo cuentan en «Rango de fechas» ───────────────────

it('las fechas que quedaron de un rango no pisan el mes elegido', function (): void {
    // Al pasar de «Rango de fechas» a «Un mes» las fechas siguen en el estado, solo que
    // escondidas. Con el filtro guardado en sesión pueden quedar ahí: manda lo elegido.
    Carbon::setTestNow('2026-08-14');

    $julio = paroEl('2026-07-10 08:00');
    $agosto = paroEl('2026-08-10 08:00');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('periodo', [
            'preset' => 'mes',
            'anio' => 2026,
            'mes' => 8,
            'desde' => '2026-07-01',
            'hasta' => '2026-07-31',
        ])
        ->assertCanSeeTableRecords([$agosto])
        ->assertCanNotSeeTableRecords([$julio]);

    Carbon::setTestNow();
});

// ── El filtro se queda puesto entre visitas ──────────────────────────────────

it('recuerda el filtro al volver a la pantalla', function (): void {
    Carbon::setTestNow('2026-08-14');

    $esteMes = paroEl('2026-08-05 08:00');
    $mesPasado = paroEl('2026-07-20 08:00');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('periodo', ['preset' => 'mes', 'anio' => 2026, 'mes' => 8]);

    // Montar la página otra vez es lo que hace un usuario al volver del menú. Sin
    // persistencia tendría que elegir el mes de nuevo cada vez.
    Livewire::test(ListDowntimeEvents::class)
        ->assertCanSeeTableRecords([$esteMes])
        ->assertCanNotSeeTableRecords([$mesPasado]);

    Carbon::setTestNow();
});

it('deja ver qué filtro está puesto, para que nadie crea que faltan datos', function (): void {
    // Con el filtro guardado entre visitas, el indicador deja de ser un adorno: es lo
    // único que separa «hay un filtro» de «se perdieron los registros».
    Carbon::setTestNow('2026-08-14');

    paroEl('2026-08-05 08:00');

    Livewire::test(ListDowntimeEvents::class)
        ->filterTable('periodo', ['preset' => 'mes', 'anio' => 2026, 'mes' => 8])
        ->assertSee('Fecha del paro: agosto 2026');

    Carbon::setTestNow();
});
