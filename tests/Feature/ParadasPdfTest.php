<?php

use App\Domain\Assets\Enums\PlantSection;
use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Enums\StoppageReason;
use App\Domain\Reports\Services\ParadasPdfService;
use App\Filament\Resources\Downtime\Pages\ListDowntimeEvents;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
 * El PDF de Paradas de Planta, que obedece al filtro de la pantalla.
 *
 * Lo que más se prueba aquí es que el documento no se contradiga a sí mismo: la tabla y
 * los gráficos salen de la misma consulta filtrada, así que un informe de una sección no
 * puede traer tortas de toda la planta.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->prensado = Area::factory()->forPlant($this->plant)->create(['name' => 'Prensado']);
    $this->calderas = Area::factory()->forPlant($this->plant)->create(['name' => 'Calderas']);

    $this->prensa = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
        'area_id' => $this->prensado->id, 'name' => 'Prensa 3',
    ]);
    $this->caldera = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
        'area_id' => $this->calderas->id, 'name' => 'Caldera principal',
    ]);

    $this->admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

function paroDeInforme(Equipment $equipo, string $inicio, float $horas): EquipmentDowntimeEvent
{
    return EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $equipo->tenant_id,
        'plant_id' => $equipo->plant_id,
        'equipment_id' => $equipo->id,
        'started_at' => Carbon::parse($inicio),
        'ended_at' => Carbon::parse($inicio)->addMinutes((int) ($horas * 60)),
    ]);
}

it('emite un PDF de verdad con los paros de la consulta', function (): void {
    paroDeInforme($this->prensa, '2026-08-05 08:00', 3);

    $bytes = app(ParadasPdfService::class)->generate(
        $this->plant,
        EquipmentDowntimeEvent::query(),
        ['Fecha del paro: este mes'],
    );

    expect($bytes)->toStartWith('%PDF');
});

// ── Que el informe no se contradiga a sí mismo ───────────────────────────────

it('no trae paros de otro equipo cuando la consulta viene filtrada', function (): void {
    // Es el test que justifica el diseño: si los gráficos se calcularan aparte, con el
    // servicio de analítica sobre el período, la tabla sería de la prensa y las tortas de
    // toda la planta. El documento diría dos cosas distintas sobre lo mismo.
    paroDeInforme($this->prensa, '2026-08-05 08:00', 3);
    paroDeInforme($this->caldera, '2026-08-06 08:00', 9);

    $filtrada = EquipmentDowntimeEvent::query()->where('equipment_id', $this->prensa->id);

    $vista = view('reports.paradas-filtradas', datosDelInforme($this->plant, $filtrada))->render();

    expect($vista)->toContain('Prensa 3')
        ->and($vista)->not->toContain('Caldera principal')
        // Tres horas, no doce: el total tampoco puede incluir lo que la tabla no muestra.
        ->and($vista)->toContain('3,00');
});

it('escribe los filtros aplicados dentro del documento', function (): void {
    paroDeInforme($this->prensa, '2026-08-05 08:00', 3);

    $vista = view('reports.paradas-filtradas', [
        ...datosDelInforme($this->plant, EquipmentDowntimeEvent::query()),
        'filtros' => ['Fecha del paro: este mes', 'Sección: Prensado'],
    ])->render();

    // Sin esto, quien reciba el PDF por correo dentro de un mes contará los paros que ve
    // como si fueran todos.
    expect($vista)->toContain('Filtros aplicados')
        ->and($vista)->toContain('Sección: Prensado');
});

it('dice que no hay filtros cuando no los hay', function (): void {
    paroDeInforme($this->prensa, '2026-08-05 08:00', 3);

    $vista = view('reports.paradas-filtradas', datosDelInforme($this->plant, EquipmentDowntimeEvent::query()))->render();

    expect($vista)->toContain('trae todos los paros registrados');
});

it('lo dice en vez de imprimir un informe vacío', function (): void {
    $vacia = EquipmentDowntimeEvent::query()->whereRaw('1 = 0');

    $vista = view('reports.paradas-filtradas', datosDelInforme($this->plant, $vacia))->render();

    expect($vista)->toContain('Ningún paro coincide con estos filtros');
});

// ── El botón de la pantalla ──────────────────────────────────────────────────

it('el botón descarga el PDF de lo que la tabla está mostrando', function (): void {
    paroDeInforme($this->prensa, '2026-08-05 08:00', 3);

    $respuesta = Livewire::test(ListDowntimeEvents::class)
        ->callAction('descargarParos')
        ->assertHasNoActionErrors();

    $descarga = $respuesta->effects['download'] ?? null;

    expect($descarga)->not->toBeNull()
        ->and($descarga['name'] ?? '')->toStartWith('PAROS-');
});

/**
 * Los datos del informe sin pasar por DomPDF, para poder mirar el HTML.
 *
 * @return array<string, mixed>
 */
function datosDelInforme(Plant $planta, $query): array
{
    $servicio = app(ParadasPdfService::class);

    // Se reusa la misma preparación que el servicio hace para el PDF: si el test armara
    // sus propios datos, probaría una vista que nadie usa.
    $reflexion = new ReflectionClass(ParadasPdfService::class);
    $agrupar = $reflexion->getMethod('agrupar');
    $agrupar->setAccessible(true);
    $porEquipo = $reflexion->getMethod('porEquipo');
    $porEquipo->setAccessible(true);
    $horas = $reflexion->getMethod('horas');
    $horas->setAccessible(true);

    $paros = (clone $query)->with(['equipment:id,code,name'])->orderBy('started_at')->get();

    return [
        'plant' => $planta,
        'filtros' => [],
        'paros' => $paros,
        'horasTotales' => round((float) $paros->sum(fn ($p): float => $horas->invoke($servicio, $p)), 2),
        'porTipo' => $agrupar->invoke($servicio, $paros, fn ($p) => $p->reported_type?->value, ReportedStoppageType::class),
        'porCategoria' => $agrupar->invoke($servicio, $paros, fn ($p) => $p->stoppage_category?->value, StoppageCategory::class),
        'porCausa' => $agrupar->invoke($servicio, $paros, fn ($p) => $p->stoppage_reason?->value, StoppageReason::class),
        'porSeccion' => $agrupar->invoke($servicio, $paros, fn ($p) => $p->section?->value, PlantSection::class),
        'porEquipo' => $porEquipo->invoke($servicio, $paros),
        'tenant' => Tenant::withoutGlobalScopes()->find($planta->tenant_id),
        'logoBase64' => null,
        'documentNumber' => 'TEST-0001',
        'documentVersion' => '1.0',
        'qrBase64' => null,
        'generatedAt' => Carbon::parse('2026-09-10 10:00'),
    ];
}
