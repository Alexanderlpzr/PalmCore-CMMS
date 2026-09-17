<?php

use App\Domain\Reports\Services\OrdenesTrabajoPdfService;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ListWorkOrders;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
 * El PDF de Órdenes de Trabajo, que obedece a lo que muestra la tabla: la pestaña, el
 * rango de fechas y los demás filtros.
 *
 * Los tests leen la consulta desde el propio componente —`getFilteredTableQuery()`, la
 * misma que usa el botón— y no una armada a mano. Si probaran una consulta propia,
 * probarían un informe que nadie descarga.
 */
beforeEach(function (): void {
    Carbon::setTestNow('2026-09-17 10:00');

    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->prensado = Area::factory()->forPlant($this->plant)->create(['name' => 'Prensado']);
    $this->equipo = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
        'area_id' => $this->prensado->id, 'name' => 'Prensa 3',
    ]);

    $this->admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

afterEach(fn () => Carbon::setTestNow());

function otDeInforme(string $planificada, string $estado = 'draft', array $extra = []): WorkOrder
{
    return WorkOrder::factory()->create([
        'tenant_id' => test()->tenant->id,
        'equipment_id' => test()->equipo->id,
        'planned_start_at' => Carbon::parse($planificada),
        'status' => $estado,
        ...$extra,
    ]);
}

/** Lo que el botón mandaría al PDF desde el estado actual de la pantalla. */
function informeDeLaPantalla($componente): array
{
    $pagina = $componente->instance();
    $filtros = (new ReflectionMethod($pagina, 'filtrosAplicados'))->invoke($pagina);

    return app(OrdenesTrabajoPdfService::class)->datos($pagina->getFilteredTableQuery(), $filtros);
}

it('el botón descarga un PDF de verdad', function (): void {
    otDeInforme('2026-09-10', extra: ['executed_by' => 'FERNANDO ARIAS']);

    $respuesta = Livewire::test(ListWorkOrders::class)
        ->callAction('descargarOrdenes')
        ->assertHasNoActionErrors();

    $descarga = $respuesta->effects['download'] ?? null;

    expect($descarga)->not->toBeNull()
        ->and($descarga['name'] ?? '')->toStartWith('OT-')
        ->and(base64_decode($descarga['content'] ?? ''))->toStartWith('%PDF');
});

it('trae solo las OT del rango de fechas elegido', function (): void {
    $dentro = otDeInforme('2026-08-10');
    $antes = otDeInforme('2026-07-31');
    $despues = otDeInforme('2026-09-01');

    $componente = Livewire::test(ListWorkOrders::class)
        ->filterTable('rango_de_fechas', ['desde' => '2026-08-01', 'hasta' => '2026-08-31']);

    $datos = informeDeLaPantalla($componente);

    expect($datos['ordenes']->pluck('id')->all())->toBe([$dentro->id])
        ->and($datos['filtros'])->toContain('Fecha planificada desde 01/08/2026')
        ->and($datos['filtros'])->toContain('Fecha planificada hasta 31/08/2026');
});

it('respeta el atajo de período igual que la tabla', function (): void {
    $esteMes = otDeInforme('2026-09-05');
    otDeInforme('2026-08-20');

    $componente = Livewire::test(ListWorkOrders::class)
        ->filterTable('rango_de_fechas', ['atajo' => 'este_mes']);

    $datos = informeDeLaPantalla($componente);

    expect($datos['ordenes']->pluck('id')->all())->toBe([$esteMes->id])
        ->and($datos['filtros'])->toContain('Fecha planificada: este mes');
});

it('obedece a la pestaña y lo escribe en el documento', function (): void {
    // La pestaña no es un filtro para Filament: si no se añadiera a mano, un PDF del
    // Histórico saldría sin decirlo y parecería trabajo pendiente.
    $abierta = otDeInforme('2026-09-05');
    $cerrada = otDeInforme('2026-09-06', 'closed', ['actual_end_at' => '2026-09-07 15:00']);

    $componente = Livewire::test(ListWorkOrders::class)->set('activeTab', 'historico');

    $datos = informeDeLaPantalla($componente);

    expect($datos['ordenes']->pluck('id')->all())->toBe([$cerrada->id])
        ->and($datos['filtros'][0])->toBe('Pestaña: Histórico')
        ->and($datos['ordenes']->pluck('id'))->not->toContain($abierta->id);
});

// ── Columnas que solo aparecen cuando dicen algo ─────────────────────────────

it('no pone la columna Estado cuando todas las OT están en el mismo', function (): void {
    otDeInforme('2026-09-05');
    otDeInforme('2026-09-06');

    $datos = app(OrdenesTrabajoPdfService::class)->datos(WorkOrder::query());
    $html = view('reports.ordenes-trabajo-filtradas', [...$datos, ...marcoDelInforme()])->render();

    // Es la razón por la que se quitó del PDF de pendientes: «Abierta» en cada fila.
    expect($datos['mostrarEstado'])->toBeFalse()
        ->and($html)->not->toContain('>Estado</th>')
        ->and($html)->toContain('Estado de todas')
        ->and($html)->not->toContain('>Fecha ejecutada</th>');
});

it('pone Estado y Fecha ejecutada cuando conviven cerradas y canceladas', function (): void {
    otDeInforme('2026-09-05', 'closed', ['actual_end_at' => '2026-09-07 15:00']);
    otDeInforme('2026-09-06', 'cancelled');

    $datos = app(OrdenesTrabajoPdfService::class)->datos(WorkOrder::query());
    $html = view('reports.ordenes-trabajo-filtradas', [...$datos, ...marcoDelInforme()])->render();

    expect($html)->toContain('>Estado</th>')
        ->and($html)->toContain('Cancelada')
        ->and($html)->toContain('>Fecha ejecutada</th>')
        ->and($html)->toContain('07/09/2026');
});

it('lleva el Responsable y no la Actividad, como el de pendientes', function (): void {
    otDeInforme('2026-09-05', extra: ['title' => 'CAMBIO DE SINFIN', 'executed_by' => 'NIVER AVILA Y ANDRES ROSAS']);

    $datos = app(OrdenesTrabajoPdfService::class)->datos(WorkOrder::query());
    $html = view('reports.ordenes-trabajo-filtradas', [...$datos, ...marcoDelInforme()])->render();

    expect($html)->toContain('>Responsable</th>')
        ->and($html)->toContain('NIVER AVILA Y ANDRES ROSAS')
        ->and($html)->not->toContain('CAMBIO DE SINFIN');
});

it('lo dice en vez de imprimir un informe vacío', function (): void {
    $datos = app(OrdenesTrabajoPdfService::class)->datos(WorkOrder::query()->whereRaw('1 = 0'));
    $html = view('reports.ordenes-trabajo-filtradas', [...$datos, ...marcoDelInforme()])->render();

    expect($html)->toContain('Ninguna orden de trabajo coincide con estos filtros')
        ->and($html)->toContain('trae todas las órdenes de trabajo');
});

// ── La rejilla de filtros ────────────────────────────────────────────────────

it('pone Sección y Equipo lado a lado, en dos tercios de la fila', function (): void {
    $filtro = Livewire::test(ListWorkOrders::class)->instance()->getTable()->getFilter('ubicacion');

    // En un tercio los dos desplegables se apilaban y Equipo caía solo en la fila de
    // abajo, que es lo que se veía mal en la pantalla.
    expect($filtro->getColumnSpan()['lg'] ?? $filtro->getColumnSpan())->toBe(2)
        ->and($filtro->getColumns()['lg'] ?? $filtro->getColumns())->toBe(2);
});

/** @return array<string, mixed> */
function marcoDelInforme(): array
{
    return [
        'tenant' => test()->tenant,
        'logoBase64' => null,
        'documentNumber' => 'OT-TEST',
        'documentVersion' => '1.0',
        'qrBase64' => null,
        'generatedAt' => now(),
    ];
}
