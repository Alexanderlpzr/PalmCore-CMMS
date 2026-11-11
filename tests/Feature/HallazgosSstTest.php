<?php

use App\Domain\Maintenance\Enums\IssueReportStatus;
use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Domain\Safety\Enums\FindingOrigin;
use App\Domain\Safety\Enums\FindingPriority;
use App\Domain\Safety\Enums\FindingStatus;
use App\Domain\Safety\Enums\ResponsibleArea;
use App\Domain\Safety\Services\SafetyFindingsDashboard;
use App\Domain\Safety\Services\SafetyFindingService;
use App\Domain\Safety\Services\SafetyFindingsWorkbookImporter;
use App\Filament\Pages\Inicio;
use App\Filament\Pages\TableroSst;
use App\Filament\Resources\Safety\Pages\CreateSstFinding;
use App\Filament\Resources\Safety\Pages\ListSstFindings;
use App\Filament\Resources\Safety\Pages\ViewSstFinding;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\Plant;
use App\Models\SstFinding;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\PermissionRegistrar;

/*
 * Los hallazgos de SST: SST los reporta, cada área responsable corrige su parte —la de
 * mantenimiento viaja como reporte de novedad → OT— y SST verifica el arreglo. Plazo por
 * prioridad desde la fecha de reporte: alta 8 días, media 15, baja 30.
 *
 * Hoy es el 5 de noviembre de 2026.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00'));
    Storage::fake(persistent_disk());

    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->esterilizacion = Area::factory()->create(['tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id, 'name' => 'Esterilización', 'code' => 'EST-01']);
    $this->recepcion = Area::factory()->create(['tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id, 'name' => 'Recepción', 'code' => 'REC-01']);

    $persona = function (string $rol, string $nombre): User {
        $user = User::factory()->create(['is_active' => true, 'name' => $nombre]);
        $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
        $user->assignRole($rol);

        return $user;
    };

    $this->sst = $persona('sst', 'Seguridad');
    $this->proceso = $persona('proceso', 'Producción');
    $this->mantenimiento = $persona('mantenimiento', 'Mantenimiento');
    $this->service = app(SafetyFindingService::class);
});

function hallazgo(array $datos = []): SstFinding
{
    return test()->service->create(test()->tenant->id, [
        'reported_on' => '2026-11-05',
        'description' => 'Escalón doblado en la subida a esterilización',
        'area_id' => test()->esterilizacion->id,
        'location_detail' => 'Sección dos',
        'priority' => FindingPriority::Alta->value,
        'action_plan' => 'Enderezar o cambiar el escalón',
        'areas' => [ResponsibleArea::Mantenimiento->value, ResponsibleArea::Proceso->value],
        ...$datos,
    ], test()->sst);
}

it('sets the deadline from the priority and opens one part per responsible area', function (): void {
    $finding = hallazgo();

    expect($finding->number)->toBe(1)
        ->and($finding->code())->toBe('H-001')
        ->and($finding->due_on->toDateString())->toBe('2026-11-13')
        ->and($finding->status)->toBe(FindingStatus::Pendiente)
        ->and($finding->tasks->pluck('area')->all())->toBe([ResponsibleArea::Mantenimiento, ResponsibleArea::Proceso]);

    expect($finding->load('area', 'equipment')->locationLabel())->toBe('Esterilización · Sección dos')
        ->and(hallazgo(['location_detail' => 'Esterilizacion'])->load('area', 'equipment')->locationLabel())->toBe('Esterilización');

    expect(hallazgo(['priority' => 'media'])->due_on->toDateString())->toBe('2026-11-20')
        ->and(hallazgo(['priority' => 'baja'])->due_on->toDateString())->toBe('2026-12-05');
});

it('sends the maintenance part as an issue report of the section infrastructure', function (): void {
    $finding = hallazgo()->load('tasks.issueReport.equipment');
    $report = $finding->tasks->firstWhere('area', ResponsibleArea::Mantenimiento)->issueReport;

    expect($report->status)->toBe(IssueReportStatus::Open)
        ->and($report->severity->value)->toBe('high')
        ->and($report->description)->toContain('Hallazgo SST H-001')
        ->and($report->equipment->code)->toBe('INF-EST-01')
        ->and($report->equipment->name)->toBe('Infraestructura — Esterilización')
        ->and($report->equipment->area_id)->toBe($this->esterilizacion->id);

    // El segundo de la misma sección reutiliza el equipo de infraestructura.
    hallazgo();
    expect(Equipment::query()->where('code', 'INF-EST-01')->count())->toBe(1);

    // Si es de un equipo, el reporte va a ese equipo.
    $tolva = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id, 'area_id' => $this->recepcion->id]);
    $deEquipo = hallazgo(['area_id' => $this->recepcion->id, 'equipment_id' => $tolva->id])->load('tasks.issueReport');
    expect($deEquipo->tasks->first()->issueReport->equipment_id)->toBe($tolva->id);
});

it('notifies process always and the responsible areas, but not who reported it', function (): void {
    hallazgo(['areas' => ['mantenimiento']]);

    expect($this->proceso->notifications()->count())->toBe(1)
        ->and($this->mantenimiento->notifications()->count())->toBe(1)
        ->and($this->sst->notifications()->count())->toBe(0)
        ->and($this->proceso->notifications()->first()->data['title'])->toContain('Nuevo hallazgo SST H-001');
});

it('moves the maintenance part with its work order and marks the finding executed when every area finishes', function (): void {
    $finding = hallazgo()->load('tasks.issueReport.equipment');
    $report = $finding->tasks->firstWhere('area', ResponsibleArea::Mantenimiento)->issueReport;
    $workOrders = app(WorkOrderService::class);

    $workOrder = $workOrders->createFromIssueReport($report, [
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => WorkOrderPriority::P2High->value,
        'title' => 'Cambiar escalón',
    ], $this->mantenimiento);

    expect($finding->fresh()->status)->toBe(FindingStatus::EnEjecucion)
        ->and($finding->tasks()->where('area', 'mantenimiento')->first()->status)->toBe(FindingStatus::EnEjecucion);

    $this->travelTo(CarbonImmutable::parse('2026-11-08 15:00'));
    $workOrders->assignTechnician($workOrder, $this->mantenimiento, TechnicianRole::Technician);
    $workOrders->transition($workOrder, WorkOrderStatus::Planned, $this->mantenimiento);
    $workOrders->transition($workOrder, WorkOrderStatus::InProgress, $this->mantenimiento);
    $workOrders->transition($workOrder, WorkOrderStatus::Completed, $this->mantenimiento, ['work_performed' => 'Escalón nuevo']);

    $parteMantenimiento = $finding->tasks()->where('area', 'mantenimiento')->first();
    expect($parteMantenimiento->status)->toBe(FindingStatus::Ejecutado)
        ->and($parteMantenimiento->executed_on->toDateString())->toBe('2026-11-08')
        ->and($finding->fresh()->status)->toBe(FindingStatus::EnEjecucion);

    // Falta proceso. Cuando termina, el hallazgo queda ejecutado con la última fecha.
    $this->service->execute($finding->tasks()->where('area', 'proceso')->first(), $this->proceso, CarbonImmutable::parse('2026-11-09'));

    $finding->refresh();
    expect($finding->status)->toBe(FindingStatus::Ejecutado)
        ->and($finding->executed_on->toDateString())->toBe('2026-11-09')
        ->and($this->sst->notifications()->get()->pluck('data.title')->all())->toContain('Hallazgo SST H-001 listo para verificar');
});

it('lets SST verify an executed finding and tells the areas', function (): void {
    $finding = hallazgo(['areas' => ['proceso']]);

    expect(fn () => $this->service->verify($finding, $this->sst))->toThrow(DomainException::class);

    $this->service->execute($finding->tasks->first(), $this->proceso);
    $this->service->verify($finding->fresh(), $this->sst, 'Quedó bien');

    expect($finding->fresh()->status)->toBe(FindingStatus::Verificado)
        ->and($finding->fresh()->verified_by)->toBe($this->sst->id)
        ->and($this->proceso->notifications()->get()->pluck('data.title')->all())->toContain('Hallazgo SST H-001 verificado');
});

it('decides who can move each part', function (): void {
    $finding = hallazgo();
    $mantenimiento = $finding->tasks->firstWhere('area', ResponsibleArea::Mantenimiento);
    $proceso = $finding->tasks->firstWhere('area', ResponsibleArea::Proceso);

    expect($this->service->canActOn($this->proceso, $proceso))->toBeTrue()
        ->and($this->service->canActOn($this->proceso, $mantenimiento))->toBeFalse()
        // La de mantenimiento se mueve con su OT, no a mano.
        ->and($this->service->canActOn($this->mantenimiento, $mantenimiento))->toBeFalse()
        ->and($this->service->canActOn($this->sst, $mantenimiento))->toBeTrue();
});

it('warns once about each overdue finding', function (): void {
    $finding = hallazgo(['reported_on' => '2026-10-20', 'areas' => ['proceso']]);
    $this->proceso->notifications()->delete();

    expect($finding->isOverdue())->toBeTrue()
        ->and($this->service->notifyOverdue())->toBe(1)
        ->and($this->service->notifyOverdue())->toBe(0)
        ->and($this->proceso->notifications()->first()->data['title'])->toBe('Hallazgo SST H-001 vencido');
});

it('sums up the findings for the SST dashboard', function (): void {
    hallazgo(['reported_on' => '2026-10-20']);
    $hecho = hallazgo(['areas' => ['proceso'], 'priority' => 'baja']);
    $this->service->execute($hecho->tasks->first(), $this->proceso);

    $summary = app(SafetyFindingsDashboard::class)->summary($this->tenant->id);

    expect($summary['total'])->toBe(2)
        ->and($summary['open'])->toBe(1)
        ->and($summary['vencidos'])->toBe(1)
        ->and($summary['porVerificar'])->toBe(1)
        ->and($summary['altaAbiertos'])->toBe(1)
        ->and(collect($summary['byArea'])->firstWhere('label', 'Proceso'))->toMatchArray(['total' => 2, 'done' => 1, 'overdue' => 1]);
});

it('imports the Excel chart with its photos: dated rows executed, pending rows to maintenance', function (): void {
    $ruta = cuadroDeHallazgos([
        [1, new DateTimeImmutable('2025-05-13'), 'Trampa de grasa sin barandas', 'Trampa de grasa lado del estirilizador ', 'BAJA', 'Instalar barandas', 'Sg-sst', 'Mantenimiento', null, null, new DateTimeImmutable('2025-06-01')],
        [11, new DateTimeImmutable('2025-10-18'), 'Cilindro sin punto de anclaje', 'Esterilizador', 'ALTO', 'Instalar anclaje', 'Sg-sst', 'Mantenimiento / Proceso', null, null, 'Pendiente'],
        [15, new DateTimeImmutable('2026-07-28'), 'Escaleras lisas', 'Areas de Proceso', 'ALTA', 'Cinta antideslizante', 'Sg-sst', 'Proceso / Sgsst', null, null, new DateTimeImmutable('2026-08-01')],
        [24],
        [27],
    ], fotos: [6 => ['I', 'J'], 9 => ['I']]);

    $summary = app(SafetyFindingsWorkbookImporter::class)->import($ruta, $this->tenant->id, $this->sst);

    expect($summary)->toMatchArray(['created' => 4, 'executed' => 2, 'pending' => 2, 'photos' => 3, 'skipped' => 0]);

    $hecho = SstFinding::query()->where('number', 1)->first();
    expect($hecho->status)->toBe(FindingStatus::Ejecutado)
        ->and($hecho->executed_on->toDateString())->toBe('2025-06-01')
        ->and($hecho->area_id)->toBe($this->esterilizacion->id)
        ->and($hecho->priority)->toBe(FindingPriority::Baja)
        ->and($hecho->tasks->first()->issue_report_id)->toBeNull()
        ->and($hecho->photo_before_path)->not->toBeNull()
        ->and($hecho->photo_after_path)->not->toBeNull();
    Storage::disk(persistent_disk())->assertExists($hecho->photo_before_path);

    $pendiente = SstFinding::query()->where('number', 11)->with('tasks.issueReport.equipment')->first();
    expect($pendiente->status)->toBe(FindingStatus::Pendiente)
        ->and($pendiente->priority)->toBe(FindingPriority::Alta)
        ->and($pendiente->tasks->pluck('area')->all())->toBe([ResponsibleArea::Mantenimiento, ResponsibleArea::Proceso])
        ->and($pendiente->tasks->first()->issueReport->equipment->code)->toBe('INF-EST-01')
        // Ya estaba vencido al importarlo: no se avisa otra vez.
        ->and($pendiente->overdue_notified_at)->not->toBeNull();

    expect(SstFinding::query()->where('number', 15)->first()->tasks->pluck('area')->all())->toBe([ResponsibleArea::Proceso, ResponsibleArea::Sst]);

    // La fila con solo foto entra sin descripción, a cargo de SST; la vacía no entra.
    $soloFoto = SstFinding::query()->where('number', 24)->first();
    expect($soloFoto->description)->toBeNull()
        ->and($soloFoto->tasks->pluck('area')->all())->toBe([ResponsibleArea::Sst])
        ->and(SstFinding::query()->where('number', 27)->exists())->toBeFalse();

    // Importar otra vez no repite nada.
    expect(app(SafetyFindingsWorkbookImporter::class)->import($ruta, $this->tenant->id, $this->sst))->toMatchArray(['created' => 0, 'skipped' => 4]);
});

it('opens the SST dashboard for the SST account and lands it there from Inicio', function (): void {
    hallazgo();
    $this->actingAs($this->sst);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(TableroSst::class)
        ->assertOk()
        ->assertSee('Hallazgos abiertos')
        ->assertSee('Por área responsable');

    Livewire::test(Inicio::class)->assertRedirect(TableroSst::getUrl());
});

it('lets SST report a finding from the form and process mark its part corrected', function (): void {
    $this->actingAs($this->sst);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(CreateSstFinding::class)
        ->fillForm([
            'reported_on' => '2026-11-05',
            'priority' => 'media',
            'description' => 'Extintor en el piso',
            'area_id' => $this->recepcion->id,
            'areas' => ['proceso'],
            'action_plan' => 'Instalar soporte',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $finding = SstFinding::query()->sole();
    expect($finding->due_on->toDateString())->toBe('2026-11-20');

    $this->actingAs($this->proceso);
    Livewire::test(ListSstFindings::class)->assertOk()->assertCanSeeTableRecords([$finding]);

    Livewire::test(ViewSstFinding::class, ['record' => $finding->getRouteKey()])
        ->assertActionVisible('ejecutar')
        ->assertActionHidden('verificar')
        ->callAction('ejecutar', ['task_id' => $finding->tasks->first()->id, 'executed_on' => '2026-11-05', 'notes' => 'Soporte instalado'])
        ->assertHasNoActionErrors();

    expect($finding->fresh()->status)->toBe(FindingStatus::Ejecutado)
        ->and($finding->fresh()->executed_on->toDateString())->toBe('2026-11-05');

    // Proceso no reporta ni verifica.
    expect($this->proceso->can('create', SstFinding::class))->toBeFalse();
});

/**
 * Un cuadro como el de SST: la fila 5 de títulos y los hallazgos desde la 6, con fotos
 * ancladas en I (antes) y J (después) de las filas pedidas.
 *
 * @param  list<list<mixed>>  $filas
 * @param  array<int, list<string>>  $fotos
 */
function cuadroDeHallazgos(array $filas, array $fotos): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'hallazgos').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($ruta);
    $writer->addRow(Row::fromValues(['', '', '', 'EXTRACTORA EL PAJUIL']));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['', '', '', 'CUADRO DE HALLAZGOS - SG-SST / AMBIENTAL']));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['ITM', 'FECHA DE REPORTE', 'DESCRIPCIÓN', 'UBICACIÓN', 'PRIORIDAD', 'PLAN DE ACCCION', 'AREA QUE REPORTA', 'AREA RESPONSABLE', 'ANTES', 'DESPUES', 'FECHA DE CORRECION']));

    foreach ($filas as $fila) {
        $writer->addRow(Row::fromValues($fila));
    }

    $writer->close();

    // Un PNG de 1×1 por foto, anclado como lo deja Excel.
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    $anchors = '';
    $rels = '';
    $n = 0;

    foreach ($fotos as $filaExcel => $columnas) {
        foreach ($columnas as $columna) {
            $n++;
            $col = $columna === 'I' ? 8 : 9;
            $row = $filaExcel - 1;
            $anchors .= "<xdr:twoCellAnchor editAs=\"oneCell\"><xdr:from><xdr:col>{$col}</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>{$row}</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from><xdr:to><xdr:col>{$col}</xdr:col><xdr:colOff>10</xdr:colOff><xdr:row>{$row}</xdr:row><xdr:rowOff>10</xdr:rowOff></xdr:to><xdr:pic><xdr:blipFill><a:blip r:embed=\"rId{$n}\"/></xdr:blipFill></xdr:pic><xdr:clientData/></xdr:twoCellAnchor>";
            $rels .= "<Relationship Id=\"rId{$n}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/image\" Target=\"../media/image{$n}.png\"/>";
        }
    }

    $zip = new ZipArchive;
    $zip->open($ruta);
    $zip->addFromString('xl/drawings/drawing1.xml', '<?xml version="1.0" encoding="UTF-8"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.$anchors.'</xdr:wsDr>');
    $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');

    for ($i = 1; $i <= $n; $i++) {
        $zip->addFromString("xl/media/image{$i}.png", $png);
    }

    $zip->close();

    return $ruta;
}

it('gives the environmental account the same as SST: reports, notices, its dashboard and the SST part', function (): void {
    $ambiental = User::factory()->create(['is_active' => true]);
    $ambiental->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $ambiental->assignRole('ambiental');

    expect($ambiental->can('create', SstFinding::class))->toBeTrue();

    // Lo que reporta Proceso con SST responsable le llega y lo puede mover.
    $finding = $this->service->create($this->tenant->id, [
        'reported_on' => '2026-11-05', 'description' => 'Derrame de aceite en el patio', 'priority' => 'alta', 'areas' => ['sst'],
    ], $this->proceso);

    expect($ambiental->notifications()->count())->toBe(1)
        ->and($this->service->canActOn($ambiental, $finding->tasks->first()))->toBeTrue()
        ->and(SafetyFindingService::areasOfUser($ambiental))->toBe(['sst']);

    $this->service->execute($finding->tasks->first(), $ambiental);
    expect($ambiental->notifications()->get()->pluck('data.title')->all())->toContain('Hallazgo SST H-001 listo para verificar');

    $this->actingAs($ambiental);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
    Livewire::test(Inicio::class)->assertRedirect(TableroSst::getUrl());
});

it('records whether each finding comes from SST or the environmental account, and says so everywhere', function (): void {
    $ambiental = User::factory()->create(['is_active' => true, 'name' => 'Gestora ambiental']);
    $ambiental->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $ambiental->assignRole('ambiental');

    $deSst = hallazgo(['areas' => ['proceso']]);
    $deAmbiental = $this->service->create($this->tenant->id, [
        'reported_on' => '2026-11-05', 'description' => 'Derrame de ACPM junto al tanque', 'area_id' => $this->esterilizacion->id,
        'priority' => 'alta', 'areas' => ['mantenimiento'],
    ], $ambiental);

    // El origen sale de quien lo reporta.
    expect($deSst->origin)->toBe(FindingOrigin::Sst)
        ->and($deAmbiental->origin)->toBe(FindingOrigin::Ambiental);

    // A mantenimiento le llega firmado por Gestión Ambiental, y solo un aviso: el del hallazgo.
    $report = $deAmbiental->load('tasks.issueReport')->tasks->first()->issueReport;
    expect($report->reporter_position)->toBe('Gestión Ambiental')
        ->and($report->reporter_name)->toBe('Gestora ambiental')
        ->and($report->description)->toContain('Hallazgo ambiental H-002')
        ->and($this->mantenimiento->notifications()->count())->toBe(1)
        ->and($this->mantenimiento->notifications()->sole()->data['title'])->toContain('Nuevo hallazgo ambiental H-002');

    // El Tablero los cuenta por separado.
    $summary = app(SafetyFindingsDashboard::class)->summary($this->tenant->id);
    expect(collect($summary['byOrigin'])->firstWhere('origin', 'ambiental'))->toMatchArray(['total' => 1, 'open' => 1])
        ->and(collect($summary['byOrigin'])->firstWhere('origin', 'sst'))->toMatchArray(['total' => 1, 'open' => 1]);

    // En la lista se filtran.
    $this->actingAs($ambiental);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(ListSstFindings::class)
        ->filterTable('origin', 'ambiental')
        ->assertCanSeeTableRecords([$deAmbiental])
        ->assertCanNotSeeTableRecords([$deSst]);

    Livewire::test(TableroSst::class)->assertOk()->assertSee('Ambiental · 1 hallazgo');

    // El formulario lo propone según la cuenta.
    Livewire::test(CreateSstFinding::class)
        ->assertSchemaStateSet(['origin' => 'ambiental'])
        ->fillForm(['reported_on' => '2026-11-05', 'priority' => 'baja', 'description' => 'Residuos sin clasificar', 'areas' => ['sst']])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SstFinding::query()->where('description', 'Residuos sin clasificar')->sole()->origin)->toBe(FindingOrigin::Ambiental);
});

it('imports the environmental chart after the SST one without clashing numbers', function (): void {
    $sst = cuadroDeHallazgos([
        [1, new DateTimeImmutable('2026-10-01'), 'Escalera sin pasamanos', 'Esterilizador', 'ALTA', 'Instalar pasamanos', 'Sg-sst', 'Proceso', null, null, 'Pendiente'],
        [2, new DateTimeImmutable('2026-10-02'), 'Extintor vencido', 'Recepción', 'MEDIA', 'Recargar', 'Sg-sst', 'Sgsst', null, null, 'Pendiente'],
    ], fotos: []);
    $ambiental = cuadroDeHallazgos([
        [1, new DateTimeImmutable('2026-10-10'), 'Aceite usado sin contención', 'Esterilizador', 'ALTA', 'Construir dique', 'Ambiental', 'Proceso', null, null, 'Pendiente'],
        [2, new DateTimeImmutable('2026-10-11'), 'Residuos mezclados', 'Recepción', 'BAJA', 'Puntos ecológicos', 'Ambiental', 'Sgsst', null, null, new DateTimeImmutable('2026-10-20')],
    ], fotos: []);
    $importer = app(SafetyFindingsWorkbookImporter::class);

    $importer->import($sst, $this->tenant->id, $this->sst);
    $summary = $importer->import($ambiental, $this->tenant->id, $this->sst, FindingOrigin::Ambiental);

    expect($summary)->toMatchArray(['created' => 2, 'skipped' => 0]);

    $dique = SstFinding::query()->where('description', 'Aceite usado sin contención')->sole();
    expect($dique->origin)->toBe(FindingOrigin::Ambiental)
        ->and($dique->number)->toBe(3)
        ->and($dique->source_item)->toBe(1)
        ->and(SstFinding::query()->where('description', 'Residuos mezclados')->sole()->number)->toBe(4)
        ->and(SstFinding::query()->where('number', 1)->sole()->origin)->toBe(FindingOrigin::Sst);

    // Cada cuadro se puede volver a importar sin repetir nada.
    expect($importer->import($ambiental, $this->tenant->id, $this->sst, FindingOrigin::Ambiental))->toMatchArray(['created' => 0, 'skipped' => 2])
        ->and($importer->import($sst, $this->tenant->id, $this->sst))->toMatchArray(['created' => 0, 'skipped' => 2])
        ->and(SstFinding::query()->count())->toBe(4);
});
