<?php

use App\Domain\Analytics\Services\ProductionCalendarService;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\ProductionCalendar\Pages\CapturaDiaria;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/**
 * La producción se anota día a día, y el mes se ve acumulándose debajo.
 *
 * Antes se pedía la semana entera de una vez. Se cambió porque obligaba a esperar al
 * domingo para saber por dónde iba el RFF, y la planta cierra el día cuando lo cierra.
 * Por dentro no cambió nada: sigue siendo una fila por día, que es la unidad de la que
 * cuelgan la eficiencia, el MTBF y el cierre mensual.
 */
beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->service = app(ProductionCalendarService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->user = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

// ── El servicio ──────────────────────────────────────────────────────────────

it('escribe la jornada del día', function (): void {
    $dia = $this->service->upsertDay($this->plant, Carbon::parse('2026-08-19'), 22, 336.04);

    expect($dia->programmed_hours)->toBe(22.0)
        ->and($dia->processed_tons)->toBe(336.04);
});

it('corregir el mismo día no crea una segunda fila', function (): void {
    $this->service->upsertDay($this->plant, Carbon::parse('2026-08-19'), 22, 300);
    $this->service->upsertDay($this->plant, Carbon::parse('2026-08-19'), 22, 336.04);

    expect(ProductionCalendarDay::withoutGlobalScopes()->count())->toBe(1)
        ->and(ProductionCalendarDay::withoutGlobalScopes()->first()->processed_tons)->toBe(336.04);
});

it('no escribe el día que se deja sin horas', function (): void {
    // Cero y «no sé» no son lo mismo: un día sin fila es un día del que no sabemos nada.
    $dia = $this->service->upsertDay($this->plant, Carbon::parse('2026-08-19'), null, 300);

    expect($dia)->toBeNull()
        ->and(ProductionCalendarDay::withoutGlobalScopes()->count())->toBe(0);
});

it('acepta un día programado en cero', function (): void {
    // Un domingo sin molienda es un dato legítimo que baja el denominador.
    $dia = $this->service->upsertDay($this->plant, Carbon::parse('2026-08-23'), 0, 0);

    expect($dia->programmed_hours)->toBe(0.0);
});

it('rechaza una jornada de más de 24 horas', function (): void {
    expect(fn () => $this->service->upsertDay($this->plant, Carbon::parse('2026-08-19'), 30, 300))
        ->toThrow(BusinessRuleException::class);
});

it('rechaza la fruta en kilogramos', function (): void {
    expect(fn () => $this->service->upsertDay($this->plant, Carbon::parse('2026-08-19'), 22, 336_040))
        ->toThrow(BusinessRuleException::class);
});

// ── El mes, con el RFF acumulándose ──────────────────────────────────────────

it('acumula el RFF día a día, que es para lo que se pidió', function (): void {
    foreach (['2026-08-03' => 196.35, '2026-08-04' => 223.75, '2026-08-05' => 246.00] as $f => $t) {
        $this->service->upsertDay($this->plant, Carbon::parse($f), 16, $t);
    }

    $mes = $this->service->month($this->plant, Carbon::parse('2026-08-15'));
    $porFecha = collect($mes['days'])->keyBy('date');

    expect($porFecha['2026-08-03']['accumulated_tons'])->toBe(196.35)
        ->and($porFecha['2026-08-04']['accumulated_tons'])->toBe(420.1)
        ->and($porFecha['2026-08-05']['accumulated_tons'])->toBe(666.1)
        ->and($mes['total_tons'])->toBe(666.1)
        ->and($mes['total_hours'])->toBe(48.0);
});

it('deja en null los días sin cargar, sin arrastrar el acumulado', function (): void {
    $this->service->upsertDay($this->plant, Carbon::parse('2026-08-03'), 16, 196.35);

    $mes = $this->service->month($this->plant, Carbon::parse('2026-08-15'));
    $porFecha = collect($mes['days'])->keyBy('date');

    // Repetir el acumulado en un día sin cargar haría parecer que ese día produjo cero.
    expect($porFecha['2026-08-04']['accumulated_tons'])->toBeNull()
        ->and($porFecha['2026-08-04']['tons'])->toBeNull()
        ->and($porFecha['2026-08-04']['hours'])->toBeNull();
});

it('no pinta días que todavía no han ocurrido', function (): void {
    $mes = $this->service->month($this->plant, Carbon::today()->startOfMonth());

    expect(collect($mes['days'])->last()['date'])->toBe(Carbon::today()->toDateString());
});

it('solo cuenta los días de la planta elegida', function (): void {
    $otra = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->service->upsertDay($this->plant, Carbon::parse('2026-08-03'), 16, 196.35);
    $this->service->upsertDay($otra, Carbon::parse('2026-08-03'), 20, 999);

    expect($this->service->month($this->plant, Carbon::parse('2026-08-15'))['total_tons'])
        ->toBe(196.35);
});

// ── La pantalla ──────────────────────────────────────────────────────────────

it('guarda la jornada desde la pantalla', function (): void {
    Livewire::test(CapturaDiaria::class)
        ->set('data.calendar_date', '2026-08-19')
        ->set('data.programmed_hours', 22)
        ->set('data.processed_tons', 336.04)
        ->call('save')
        ->assertHasNoErrors();

    $dia = ProductionCalendarDay::withoutGlobalScopes()->where('calendar_date', '2026-08-19')->first();

    expect($dia->processed_tons)->toBe(336.04);
});

it('muestra el mes con el acumulado bajo el formulario', function (): void {
    $this->service->upsertDay($this->plant, Carbon::parse('2026-08-03'), 16, 196.35);
    $this->service->upsertDay($this->plant, Carbon::parse('2026-08-04'), 16, 223.75);

    Livewire::test(CapturaDiaria::class)
        ->set('data.calendar_date', '2026-08-19')
        ->assertSee('RFF ACUMULADO')
        ->assertSee('TOTAL DEL MES')
        // 196,35 + 223,75
        ->assertSee('420,10');
});

it('precarga lo que ya estaba anotado ese día', function (): void {
    $this->service->upsertDay($this->plant, Carbon::parse('2026-08-19'), 22, 336.04);

    Livewire::test(CapturaDiaria::class)
        ->set('data.calendar_date', '2026-08-19')
        ->assertSet('data.programmed_hours', 22.0)
        ->assertSet('data.processed_tons', 336.04);
});

it('lleva el formulario al día que se pulsa en la tabla', function (): void {
    $this->service->upsertDay($this->plant, Carbon::parse('2026-08-03'), 16, 196.35);

    Livewire::test(CapturaDiaria::class)
        ->set('data.calendar_date', '2026-08-19')
        ->call('goToDay', '2026-08-03')
        ->assertSet('data.calendar_date', '2026-08-03')
        ->assertSet('data.processed_tons', 196.35);
});

it('avisa en vez de escribir cuando la fruta viene en kilos', function (): void {
    Livewire::test(CapturaDiaria::class)
        ->set('data.calendar_date', '2026-08-19')
        ->set('data.programmed_hours', 22)
        ->set('data.processed_tons', 336_040)
        ->call('save');

    expect(ProductionCalendarDay::withoutGlobalScopes()->count())->toBe(0);
});

it('se la esconde a quien no tiene el permiso', function (): void {
    $this->seed(TenantRolesSeeder::class);

    $ajeno = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $ajeno->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->actingAs($ajeno);

    Livewire::test(CapturaDiaria::class)->assertForbidden();
});
